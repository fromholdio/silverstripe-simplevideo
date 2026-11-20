<?php

namespace Fromholdio\SimpleVideo\Model;

use Embed\Embed;
use Exception;
use Fromholdio\ExternalURLField\ExternalURLField;
use Psr\Log\LoggerInterface;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\HTML;
use UncleCheese\DisplayLogic\Forms\Wrapper;

class SimpleVideo extends DataObject
{
    private static string $table_name = 'SimpleVideo';

    private static string $singular_name = 'Video';

    private static string $plural_name = 'Videos';

    private static array $extensions = [
        Versioned::class
    ];

    private static array $db = [
        'Title' => 'Varchar',
        'Description' => 'Text',
        'SourceURL' => 'ExternalURL',
        'EmbedThumbnailURL' => 'ExternalURL',
        'EmbedHTML' => 'HTMLText',
        'EmbedWidth' => 'Int',
        'EmbedHeight' => 'Int',
        'EmbedAspectRatio' => 'Decimal',
        'ProviderURL' => 'ExternalURL',
        'ImageMode' => 'Varchar(10)'
    ];

    private static array $has_one = [
        'CustomThumbnail' => Image::class
    ];

    private static array $defaults = [
        'ImageMode' => 'embed'
    ];

    private static array $owns = [
        'CustomThumbnail'
    ];

    private static array $summary_fields = [
        'ImageCMSThumbnail',
        'Title'
    ];

    private static array $field_labels = [
        'CustomThumbnail' => 'Custom Image',
    ];

    public function getImageCMSThumbnail()
    {
        $thumb = null;
        if ($this->ImageMode === 'embed') {
            $thumb = DBField::create_field(
                'HTMLFragment',
                HTML::createTag('img', ['src' => $this->EmbedThumbnailURL, 'width' => '75'])
            );
        } elseif ($thumnail = $this->getCustomThumbnail()) {
            $thumb = $thumnail->ThumbnailIcon(75, 40);
        }

        return $thumb;
    }

    public function getCMSFields()
    {
        if ($this->isInDB() && $this->SourceURL) {
            $fields = FieldList::create(
                TabSet::create(
                    'Root',
                    Tab::create(
                        'Main',
                        TextField::create('Title', $this->fieldLabel('Title')),
                        TextareaField::create('Description', $this->fieldLabel('Description')),
                        FieldGroup::create(
                            'Preview',
                            LiteralField::create('EmbedPreview', $this->EmbedHTML)
                        )
                    ),
                    Tab::create(
                        'Image',
                        OptionsetField::create(
                            'ImageMode',
                            $this->fieldLabel('ImageMode'),
                            [
                                'embed' => 'Use Provider Thumbnail Image',
                                'custom' => 'Upload Custom Image'
                            ]
                        ),
                        $thumbPreviewField = Wrapper::create(
                            FieldGroup::create(
                                'Provider Thumbnail',
                                LiteralField::create(
                                    'ThumbnailPreview',
                                    '<img src="'
                                    . $this->EmbedThumbnailURL
                                    . '" style="width: 300px; height: auto;"'
                                    . '>'
                                )
                            )
                        ),
                        $customImageWrapper = Wrapper::create(
                            $customImageField = UploadField::create(
                                'CustomThumbnail',
                                $this->fieldLabel('CustomThumbnail')
                            )
                        )
                    ),
                    Tab::create(
                        'Embed',
                        ReadonlyField::create('EmbedWidth'),
                        ReadonlyField::create('EmbedHeight'),
                        ReadonlyField::create('EmbedAspectRatio'),
                        TextareaField::create('EmbedHTML')
                    ),
                    Tab::create(
                        'Source',
                        ExternalURLField::create('SourceURL'),
                        FieldGroup::create(
                            'Refresh?',
                            CheckboxField::create('DoRefreshFromSource')
                        )
                    )
                )
            );

            $thumbPreviewField->displayIf('ImageMode')->isEqualTo('embed');
            $customImageWrapper->displayIf('ImageMode')->isEqualTo('custom');
            $customImageField->setAllowedFileCategories('image');
        } else {
            $fields = FieldList::create(
                TabSet::create(
                    'Root',
                    Tab::create(
                        'Main',
                        ExternalURLField::create(
                            'SourceURL',
                            $this->fieldLabel('SourceURL')
                        ),
                        HiddenField::create('DoRefreshFromSource', null, 1)
                    )
                )
            );
        }

        return $fields;
    }

    public function saveDoRefreshFromSource($value): void
    {
        if ($value) {
            $this->doRefreshFromSource();
            $this->DoRefreshFromSource = 0;
        }
    }

    public function doRefreshFromSource(): bool
    {
        if (!$this->SourceURL) {
            return false;
        }

        try {
            $embed = new Embed();
            $embedInfo = $embed->get($this->SourceURL);

            $this->Title = (string)$embedInfo->title ?: '';
            $this->Description = (string)$embedInfo->description ?: '';

            $this->EmbedThumbnailURL = (string)$embedInfo->image ?: '';
            $this->EmbedHTML = (string)$embedInfo->code->html ?: '';
            $this->EmbedWidth = (int)$embedInfo->code->width ?: '';
            $this->EmbedHeight = (int)$embedInfo->code->height ?: '';
            $this->EmbedAspectRatio = (float)$embedInfo->code->ratio ?: '';
            $this->ProviderURL = (string)$embedInfo->url ?: '';

            return true;
        } catch (Exception $exception) {
            Injector::inst()->get(LoggerInterface::class)->error('SimpleVideo::doRefreshFromSource: ' . $exception->getMessage());
            $this->SourceURL = '';
            return false;
        }
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if (!$this->SourceURL || empty($this->SourceURL)) {
            $result->addError('You must provide a valid Source URL');
        }

        return $result;
    }

    public function getCustomThumbnail()
    {
        return $this->getComponent('CustomThumbnail');
    }

    public function getEmbedAspectRatioPercent(): ?DBField
    {
        $aspectRatio = $this->EmbedAspectRatio;
        if (!$aspectRatio) {
            if (!$this->EmbedWidth || !$this->EmbedHeight) {
                return null;
            }

            $aspectRatio = $this->EmbedHeight / $this->EmbedWidth * 100;
        }

        return DBField::create_field('Percentage', $aspectRatio / 100, null, 2);
    }

    public function forTemplate(): string
    {
        return null;
    }
}

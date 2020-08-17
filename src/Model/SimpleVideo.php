<?php

namespace Fromholdio\SimpleVideo\Model;

use BurnBright\ExternalURLField\ExternalURLField;
use Embed\Adapters\Adapter;
use Embed\Embed;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
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
    private static $table_name = 'SimpleVideo';
    private static $singular_name = 'Video';
    private static $plural_name = 'Videos';

    private static $extensions = [
        Versioned::class
    ];

    private static $db = [
        'Title' => 'Varchar',
        'Description' => 'Text',
        'SourceURL' => 'ExternalURL',
        'EmbedThumbnailURL' => 'ExternalURL',
        'EmbedHTML' => 'HTMLText',
        'EmbedWidth' => 'Int',
        'EmbedHeight' => 'Int',
        'EmbedAspectRatio' => 'Decimal',
        'ProviderURL' => 'ExternalURL',
        'ProviderVideoID' => 'Varchar',
        'ImageMode' => 'Varchar(10)'
    ];

    private static $has_one = [
        'CustomThumbnail' => Image::class
    ];

    private static $defaults = [
        'ImageMode' => 'embed'
    ];

    private static $owns = [
        'CustomThumbnail'
    ];

    private static $summary_fields = [
        'ImageCMSThumbnail',
        'Title'
    ];
	
    private static $field_labels = [
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
        }
        else if ($this->CustomThumbnailID) {
            $thumb = $this->CustomThumbnail()->ThumbnailIcon(75,40);
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
                        TextField::create('ProviderVideoID'),
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

        }
        else {
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

    public function saveDoRefreshFromSource($value)
    {
        if ($value) {
            $this->doRefreshFromSource();
            $this->DoRefreshFromSource = 0;
        }
    }

    public function doRefreshFromSource()
    {
        if (!$this->SourceURL) {
            return false;
        }

        $embed = Embed::create(
            $this->SourceURL,
            [
                'choose_bigger_image' => true
            ]
        );

        if ($embed && $embed instanceof Adapter) {
            $this->Title = $embed->title;
            $this->Description = $embed->description;
            $this->EmbedThumbnailURL = $embed->image;
            $this->EmbedHTML = $embed->code;
            $this->EmbedWidth = $embed->width;
            $this->EmbedHeight = $embed->height;
            $this->EmbedAspectRatio = $embed->aspectRatio;
            $this->ProviderURL = $embed->url;

            $providers = $embed->getProviders();
            if (isset($providers['oembed'])) {
                $oembed = $providers['oembed'];
                $this->ProviderVideoID = $oembed->getBag()->get('video_id');
            }

            return true;
        }

        $this->SourceURL = '';
        return false;
    }

    public function validate()
    {
        $result = parent::validate();
        if (!$this->SourceURL || empty($this->SourceURL)) {
            $result->addError('You must provide a valid Source URL');
        }
        return $result;
    }

    public function getEmbedAspectRatioPercent()
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

    public function forTemplate()
    {
        return null;
    }
}

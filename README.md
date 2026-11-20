# silverstripe-simplevideo

A small SilverStripe module providing a Video object, created by supplying an oEmbed-compatible video URL, which pulls 
metadata into object locally.

## Requirements

* [silverstripe-framework](https://github.com/silverstripe/silverstripe-framework) ^6

* Current major 4.x supports Silverstripe ^6
* Please use 3.x branch for Silverstripe ^4.11 and ^5
* Please use 2.x branch if for some ungodly reason you are constrained to either CMS <4.11 or fromholdio/externalurlfield <1.1.0, else use 3.x
* Please use 1.x branch for silverstripe/cms up to 4.10.x.

## Installation

`composer require fromholdio/silverstripe-simplevideo`

## Details & Usage

You can use the `SimpleVideo` class in `has_one` and `has_many` replationships.

For `has_one`, we recommend using the [fromholdio/silverstripe-minigridfield](https://github.com/fromholdio/silverstripe-minigridfield) 
module to manage the video:

```php
    private static $has_one = [
        'Video' => SimpleVideo::class,
    ];

    ...

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        ...

        $fields->removeByName([
            'VideoID',
        ]);

        $fields->push(
            HasOneMiniGridField::create(
                'Video',
                'Video',
                $this
            )
        );

        ...

        return $fields;
    }
```

in the template, in it's simplest form, you can use the video like this:

```html
<% if $Video %>
    <% with $Video %>
        <div class="VideoEmbed" style="aspect-ratio: $EmbedWidth / $EmbedHeight;">
            $EmbedHTML
        </div>
    <% end_with %>
<% end_if %>
```

You will then need to set the contained iframe or video to fill out the space of the container, e.g.:

```css
.VideoEmbed > * {
    width: 100%;
    height: 100%;
}
```

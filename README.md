# Azbox PHP
The library to integrate Azbox translation to a PHP website


## Getting Started

### Install

Install the package via [Composer](https://getcomposer.org/doc/00-intro.md):

```bash
composer require azbox/azbox-php
```

(If you don't use Composer, you can copy the `azbox.php` file and the `lib` directory to your project).

### Initialize
To initialize Azbox, you need your API Key and Project ID. You can find them on [your Azbox account](https://panel.azbox.io/register?source=git).

Enter Azbox initialization code at the beginning of the execution (Usually index.php or app.php)

```php
// composer autoload
require __DIR__ . '/vendor/autoload.php';
// if you are not using composer: require_once 'path/to/azbox.php';

\Azbox\AZ::Instance(array(
	"api_key" => "YOUR API KEY", // The api key, you can get one on https://panel.azbox.io/register
	"projectId" => "YOUR PROJECT ID", // Your project ID from the Azbox panel
	"original_language" => "en", // the original language of your website
	"destination_languages" => "es,fr,de", // the languages you want to translate your website into
	"buttonOptions" => array(
        "fullname" => true,
		"is_dropdown" => true,
        "position" => "bottom-right",
    ),
));
```

### Check it works !
Now, when you go on your website, you should see a language selector at the bottom right of your website. When you click on it, you can switch between languages and your website will be translated automatically.

## Example

A complete working example is available in the [`example/`](example/) directory. This example demonstrates:

- How to initialize Azbox with your API key and Project ID
- How to configure the language selector
- How to set up the `home_url` parameter for subdirectories
- A simple HTML page that can be translated

To try the example:

1. Copy the `example/` directory to your web server
2. Update the `api_key` and `projectId` in `example/index.php` with your own credentials
3. Adjust the `home_url` parameter if needed
4. Open the page in your browser

## Customize

### Button position
By default, the language button appears at the bottom right of your website. You can change its position using the `button_position` parameter:

- `"bottom-left"` - Bottom left corner
- `"bottom-center"` - Bottom center
- `"bottom-right"` - Bottom right corner (default)
- `"top-left"` - Top left corner
- `"top-center"` - Top center
- `"top-right"` - Top right corner

You can also place the button anywhere you want in your HTML page. Just enter `<div id="azbox_here"></div>` in your HTML wherever you want the button to be.

You can also customize it by adding some CSS rules on the button's element.


### Parameters

#### Required
- `api_key` (string) Your API key that gives you access to Azbox services. You can get one by [creating an account](https://panel.azbox.io/register)
- `projectId` (string) Your project ID from the Azbox panel
- `original_language` (string) The language of your original website. Enter the two letter code (e.g., "en", "es", "fr"). See the [complete list of supported languages](SUPPORTED_LANGUAGES.md)
- `destination_languages` (string) The languages you want to translate into. Enter the two letter codes separated by commas (e.g., "es,fr,de"). See the [complete list of supported languages](SUPPORTED_LANGUAGES.md)

#### Optional
- `button_position` (string, default `"bottom-right"`) Position of the language selector button. Options: `"bottom-left"`, `"bottom-center"`, `"bottom-right"`, `"top-left"`, `"top-center"`, `"top-right"`

- `buttonOptions` (array) An array of parameters to customize the language button design
	- `is_dropdown` (bool, default `true`) `true` if the button is a dropdown list, `false` to show all languages as a list 
	- `with_name` (bool, default `true`) `true` to show the name of the language in the button
	- `fullname` (bool, default `true`) `true` to show the full name of the language in the button (English, Français,...), `false` to show the language code (EN, FR,...)

- `exclude_blocks` (string, default "") Comma separated list of CSS selectors. You can exclude parts of your website from being translated.

- `exclude_url` (string, default "") Comma separated list of **relative** URLs. You can exclude URLs of your website from being translated.

- `home_url` (string, default "") Enter the subdirectory if your website is not at the root. For instance, if your website is at `http://localhost/website/`, then enter `/website`


#### Example
Here is an example of initialization code

```php
// Example: Your website is in English, and you want it also in Spanish, French, and German

\Azbox\AZ::Instance(array(
	"api_key" => "YOUR API KEY", // The api key, you can get one on https://panel.azbox.io/register
	"projectId" => "YOUR PROJECT ID", // Your project ID from the Azbox panel
	"original_language" => "en", 
	"destination_languages" => "es,fr,de", 
	"button_position" => "top-right", // Position the button at the top right
	"buttonOptions" => array(
		"fullname" => false,
		"with_name" => true,
		"is_dropdown" => true
	),
	"exclude_blocks" => ".logo,nav #brand",
	"exclude_url" => "/terms-conditions,/privacy-policy",
	"home_url" => "/my-website" // If your site is in a subdirectory
));
```

## Performance

Azbox automatically caches translations in your visitors' browsers to provide faster language switching. Once a page is translated, subsequent language changes will be instant without needing to reload from the server. This improves the user experience and reduces server load.

## Troubleshooting
Once you save the initialization code, you should see the language button appear at the bottom right of your website.

If that is not the case, it means the Azbox code is not running. Check if you have PHP errors

If you see the flags but when you switch languages, you see a 404 /Not found, it means Azbox code is not running or not at the beginning. Azbox needs to run before the request is processed so make sure it is included at the beginning of the PHP code.

Also, make sure that your rewrite rules are configured so that the PHP code is run on a 404 page.

And of course, finally, contact us at support@azbox.io or on the live chat on our website, we answer pretty fast :)

<?php
/**
 * Azbox PHP Usage Example
 * 
 * This is a simple example of how to use the Azbox component
 * to translate a web page.
 */

// Option 1: If using Composer (recommended)
// require __DIR__ . '/../vendor/autoload.php';

// Option 2: If not using Composer, load directly
require_once __DIR__ . '/../azbox.php';

// Initialize Azbox
// IMPORTANT: Replace 'YOUR_API_KEY' and 'YOUR_PROJECT_ID' with your real API key and project ID
// You can get them at: https://panel.azbox.io/register
// Supported languages: https://github.com/azbox-io/azbox-php/blob/main/SUPPORTED_LANGUAGES.md
\Azbox\AZ::Instance(array(
    "api_key" => "YOUR_API_KEY", // Replace with your API key
    "project_id" => "YOUR_PROJECT_ID", // Replace with your project ID
    "original_language" => "en", // Original language of the page (English)
    "destination_languages" => "es,fr,de", // Languages to translate to (Spanish, French, German)
    "home_url" => "/", // IMPORTANT: Set this to your subdirectory path
    "button_options" => array(
        "fullname" => false,
        "is_dropdown" => true,
        "position" => "bottom-right",
    ),
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Azbox Example - Demo Page</title>
    <meta name="description" content="This is an example page to demonstrate how Azbox PHP works">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        
        h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .description {
            color: #666;
            font-size: 1.2em;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 1.1em;
            border-radius: 50px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .button:active {
            transform: translateY(0);
        }
        
        .info {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #666;
            font-size: 0.9em;
        }
        
        .info strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to Azbox</h1>
        
        <p class="description">
            This is an example page that demonstrates how the Azbox 
            translation component works. You can change the language using the language 
            selector that appears in the bottom right corner of the page.
        </p>
        
        <button class="button" type="button">
            Click here
        </button>
        
        <div class="info">
            <strong>Note:</strong> For the translation to work, you need to configure 
            your Azbox API key in the <code>index.php</code> file. 
            You can get a free API key at 
            <a href="https://panel.azbox.io/register" target="_blank">panel.azbox.io</a>
        </div>
    </div>
</body>
</html>


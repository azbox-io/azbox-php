# Supported Languages

This document lists all the language codes supported by Azbox translation service.

## Language Codes

Azbox supports the following languages using their language codes:

### Main Languages

| Code | Language | Notes |
|------|----------|-------|
| `AR` | Arabic | |
| `BG` | Bulgarian | |
| `CS` | Czech | |
| `DA` | Danish | |
| `DE` | German | |
| `EL` | Greek | |
| `EN-GB` | English (British) | |
| `EN-US` | English (American) | |
| `ES` | Spanish | |
| `ES-419` | Spanish (Latin American) | |
| `ET` | Estonian | |
| `FI` | Finnish | |
| `FR` | French | |
| `HE` | Hebrew | |
| `HU` | Hungarian | |
| `ID` | Indonesian | |
| `IT` | Italian | |
| `JA` | Japanese | |
| `KO` | Korean | |
| `LT` | Lithuanian | |
| `LV` | Latvian | |
| `NB` | Norwegian Bokmål | |
| `NL` | Dutch | |
| `PL` | Polish | |
| `PT-BR` | Portuguese (Brazilian) | |
| `PT-PT` | Portuguese (all Portuguese variants excluding Brazilian Portuguese) | |
| `RO` | Romanian | |
| `RU` | Russian | |
| `SK` | Slovak | |
| `SL` | Slovenian | |
| `SV` | Swedish | |
| `TH` | Thai | |
| `TR` | Turkish | |
| `UK` | Ukrainian | |
| `VI` | Vietnamese | |
| `ZH-HANS` | Chinese (simplified) | |
| `ZH-HANT` | Chinese (traditional) | |

### Additional Languages (Quality Optimized Only)*

The following languages only work with the quality_optimized model or when no model is specified. They are not compatible with requests that specify the latency_optimized model. These languages do not support glossaries or formality.

| Code | Language |
|------|----------|
| `ACE` | Acehnese |
| `AF` | Afrikaans |
| `AN` | Aragonese |
| `AS` | Assamese |
| `AY` | Aymara |
| `AZ` | Azerbaijani |
| `BA` | Bashkir |
| `BE` | Belarusian |
| `BHO` | Bhojpuri |
| `BN` | Bengali |
| `BR` | Breton |
| `BS` | Bosnian |
| `CA` | Catalan |
| `CEB` | Cebuano |
| `CKB` | Kurdish (Sorani) |
| `CY` | Welsh |
| `EO` | Esperanto |
| `EU` | Basque |
| `FA` | Persian |
| `GA` | Irish |
| `GL` | Galician |
| `GN` | Guarani |
| `GOM` | Konkani |
| `GU` | Gujarati |
| `HA` | Hausa |
| `HI` | Hindi |
| `HR` | Croatian |
| `HT` | Haitian Creole |
| `HY` | Armenian |
| `IG` | Igbo |
| `IS` | Icelandic |
| `JV` | Javanese |
| `KA` | Georgian |
| `KK` | Kazakh |
| `KMR` | Kurdish (Kurmanji) |
| `KY` | Kyrgyz |
| `LA` | Latin |
| `LB` | Luxembourgish |
| `LMO` | Lombard |
| `LN` | Lingala |
| `MAI` | Maithili |
| `MG` | Malagasy |
| `MI` | Maori |
| `MK` | Macedonian |
| `ML` | Malayalam |
| `MN` | Mongolian |
| `MR` | Marathi |
| `MS` | Malay |
| `MT` | Maltese |
| `MY` | Burmese |
| `NE` | Nepali |
| `OC` | Occitan |
| `OM` | Oromo |
| `PA` | Punjabi |
| `PAG` | Pangasinan |
| `PAM` | Kapampangan |
| `PRS` | Dari |
| `PS` | Pashto |
| `QU` | Quechua |
| `SA` | Sanskrit |
| `SCN` | Sicilian |
| `SQ` | Albanian |
| `SR` | Serbian |
| `ST` | Sesotho |
| `SU` | Sundanese |
| `SW` | Swahili |
| `TA` | Tamil |
| `TE` | Telugu |
| `TG` | Tajik |
| `TK` | Turkmen |
| `TL` | Tagalog |
| `TN` | Tswana |
| `TS` | Tsonga |
| `TT` | Tatar |
| `UR` | Urdu |
| `UZ` | Uzbek |
| `WO` | Wolof |
| `XH` | Xhosa |
| `YI` | Yiddish |
| `YUE` | Cantonese |
| `ZU` | Zulu |

\* These languages only work with the quality_optimized model or when no model is specified. They are not compatible with requests that specify the latency_optimized model. These languages do not support glossaries or formality.

## Usage Example

```php
\Azbox\AZ::Instance(array(
    "api_key" => "YOUR_API_KEY",
    "projectId" => "YOUR_PROJECT_ID",
    "original_language" => "en", // or "EN-US", "EN-GB"
    "destination_languages" => "es,fr,de,ja,pt-br", // Spanish, French, German, Japanese, Portuguese (Brazilian)
));
```

## Notes

- Use the language codes as shown in the tables above
- For English, Portuguese, Spanish, and Chinese, use the specific variants (`EN-US`, `EN-GB`, `PT-BR`, `PT-PT`, `ES-419`, `ZH-HANS`, `ZH-HANT`) when possible for better translation quality
- Separate multiple destination languages with commas (no spaces)
- The `original_language` is the language your website is currently written in
- The `destination_languages` are the languages you want to translate your website into
- Languages marked with * only work with quality_optimized model and do not support glossaries or formality
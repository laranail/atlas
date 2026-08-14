<?php

declare(strict_types=1);

/*
| Messages for the rules in src/Rules. Published to
| lang/vendor/laranail-atlas/en/validation.php.
|
| Each says what would be accepted, not only that the value was rejected: a
| message reading "the selected country is invalid" tells somebody staring at
| `UK` nothing about why, and `GB` is the answer.
*/

return [

    'country_code' => 'The :attribute must be a country code this catalogue holds — ISO 3166-1 alpha-2 (GB), alpha-3 (GBR) or numeric (826).',

    'currency_code' => 'The :attribute must be an ISO 4217 code in use by a country in this catalogue, such as USD, EUR or KES.',

    'language_code' => 'The :attribute must be an ISO 639-3 code spoken in a country in this catalogue — three letters, such as eng, swa or fra (not the two-letter en, sw, fr).',

    'coordinate' => 'The :attribute must be a latitude and longitude separated by a comma, such as 51.5074,-0.1278.',

    'latitude' => 'The latitude in :attribute must be between -90 and 90.',

];

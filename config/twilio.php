<?php

return [
    'sid'            => env('TWILIO_SID'),
    'auth_token'     => env('TWILIO_AUTH_TOKEN'),
    'whatsapp_from'  => env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'),
];

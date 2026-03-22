<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Doctor Self-Link
    |--------------------------------------------------------------------------
    | When true, a user who has enabled Doctor Mode can appear in their own
    | doctor search results (useful in single-user dev/test environments).
    | Set to false in production to prevent self-linking.
    |
    */
    'doctor_allow_self_link' => env('DOCTOR_ALLOW_SELF_LINK', false),
];

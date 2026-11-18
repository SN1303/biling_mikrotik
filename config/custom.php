<?php

// Custom Config
// -------------------------------------------------------------------------------------
//! IMPORTANT: Make sure you clear the browser local storage In order to see the config changes in the template.
//! To clear local storage: (https://www.leadshook.com/help/how-to-clear-local-storage-in-google-chrome-browser/).

return [
  'custom' => [
    'myLayout' => 'vertical', // Options[String]: vertical(default), horizontal
    'myTheme' => 'theme-default', // Options[String]: theme-default(default), theme-bordered, theme-semi-dark
    'myStyle' => 'light', // Options[String]: light(default), dark & system mode
    'myRTLSupport' => false, // options[Boolean]: true(default), false
    'myRTLMode' => false, // options[Boolean]: false(default), true
    'hasCustomizer' => true, // Display customizer or not
    'displayCustomizer' => false, // Display customizer UI or not
    'contentLayout' => 'wide', // options[String]: 'compact', 'wide' (compact=container-xxl, wide=container-fluid)
    'navbarType' => 'fixed', // options[String]: 'fixed', 'static', 'hidden' (Only for vertical Layout)
    'footerFixed' => false, // options[Boolean]: false(default), true
    'menuFixed' => true, // options[Boolean]: true(default), false
    'menuCollapsed' => false, // options[Boolean]: false(default), true
    'headerType' => 'fixed',
    'showDropdownOnHover' => false,
    'customizerControls' => [],
  ],
];

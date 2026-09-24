<?php

// Real Gmail SMTP config, used by mailer_helper.php and Login/send_code.php.
// This file is gitignored on purpose — never commit it, it has the real app password.
// See mail_config.sample.php if you need to set this up again on a new machine.

return [

    "username" => "clayandstuff.system@gmail.com",

    // TODO: palitan ito ng BAGONG App Password mula sa Google
    // (matapos mo i-revoke ang luma). Walang space sa pagitan.
    "app_password" => "qiujepgzygxfzdki",

    "from_name" => "Clay and Stuff",

];

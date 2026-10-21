<?php
// Admins are normal users with is_admin=1, so they use the same secure e-mail OTP reset flow.
header('Location: forgot_password.php');
exit();

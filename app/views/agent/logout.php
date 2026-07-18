<?php

unset($_SESSION['agent']);

header('Location: ?page=public-login');
exit;
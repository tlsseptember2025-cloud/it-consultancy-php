<?php

unset($_SESSION['agent']);

header('Location: ?page=agent-login');
exit;
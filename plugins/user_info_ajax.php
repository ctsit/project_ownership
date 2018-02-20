<?php

$result = array('success' => false);

if (isset($_GET['username'])) {
    // Remove illegal characters (if somehow posted bypassing javascript)
    $user = preg_replace('/[^a-zA-Z0-9-.@_]/', '', $_GET['username']);
    if ($user == $_GET['username'] && ($user_info = User::getUserInfo($user)) && !empty($user_info['user_email'])) {
        $result['success'] = true;
        $result['data'] = array();

        foreach (array('firstname', 'lastname', 'email') as $key) {
            $result['data'][$key] = $user_info['user_' . $key];
        }
    }
}

echo json_encode($result);

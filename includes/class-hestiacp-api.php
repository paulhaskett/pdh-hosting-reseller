<?php

if (!defined('ABSPATH')) {
    exit;
}

class Hestia_API
{
    private $server;
    private $user;
    private $key;

    public function __construct($server, $user, $key)
    {
        $this->server = rtrim($server, '/');
        $this->user   = $user;
        $this->key    = $key;
    }

    private function request($endpoint, $args = [])
    {
        $url = $this->server . '/api/' . ltrim($endpoint, '/');

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->user . ':' . $this->key),
            ],
            'body' => $args,
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Hestia API error: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function create_user($username, $password, $domain, $package)
    {
        return $this->request('account/add', [
            'username' => $username,
            'password' => $password,
            'domain'   => $domain,
            'package'  => $package,
        ]);
    }

    public function suspend_user($username)
    {
        return $this->request('account/suspend', [
            'username' => $username,
        ]);
    }

    public function delete_user($username)
    {
        return $this->request('account/delete', [
            'username' => $username,
        ]);
    }
}

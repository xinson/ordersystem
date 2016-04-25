<?php

namespace Common\Models;

/**
 * Class Client
 * @package Common\Model
 *
 * @method array|null findFirstByClient(string $client) Load client by client app key
 */
class Client extends Model
{
    public $client;

    public $id;

    public $app_secret;

}

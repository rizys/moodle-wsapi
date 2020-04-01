<?php

namespace App\Models;

use GuzzleHttp\Client;

class MWService {
    
    private $client;
    private $token;

    /**
     * MWService constructor.
     * @param $base_uri Adresse de votre Moodle
     * @param $service Nom abrégé du service à joindre
     */
    public function __construct($base_uri, $service) {
        $this->client = new Client(['base_uri' => $base_uri]);
        $this->token = $this->init($service);
    }

    /**
     * @param $function Nom de la fonction à utiliser
     * @return mixed
     */
    public function call($function) {
        return $this->post('webservice/rest/server.php', [
            'wstoken'               => $this->token,
            'wsfunction'            => $function,
            'moodlewsrestformat'    => 'json'
        ]);
    }

    /**
     * Les identifiants de l'utilisateur Moodle à utiliser ne devraient pas être écrits
     * en dur dans ce fichier mais plutôt dans des variables d'environement par exemple
     *
     * @param $service
     * @return mixed
     */
    private function init($service) {
        return $this->post('login/token.php', [
            'username'  => env('WS_USERNAME'),
            'password'  => env('WS_PASSWORD'),
            'service'   => $service
        ])->token;
    }
    
    private function post($uri, $query) {
        $response = $this->client->request('POST', $uri, [
            'query' => $query
        ]);
        if (
            $response->getStatusCode() === 200
            && strpos($response->getHeaderLine('content-type'), 'application/json') !== false
        ) {
            return json_decode($response->getBody());
        } else {
            throw new \Exception("MOODLE WEB SERVICE: bad http response");
        }
    }
    
}

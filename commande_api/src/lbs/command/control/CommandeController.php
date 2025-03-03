<?php

namespace lbs\command\control;

use Firebase\JWT\JWT;
use lbs\command\model\Client;
use lbs\command\model\Commande;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
use system\Json;

class CommandeController
{

    public function getCommand(Request $req, Response $resp, $args)
    {

        $resp = $resp->withHeader('Content-Type', 'application/json');

        $id = $args['id'];

        if (isset($_GET['token'])) {
            $token = $_GET['token'];
        } else {
            $resp = $resp->withStatus(401);
            $resp->getBody()->write(Json::error(401, "Token non fournie"));
            return $resp;
        }
        $commande = Commande::find($id);

        if ($commande) {
            if ($token == $commande->token || $_SERVER["HTTP_X_LBS_TOKEN"] == $commande->token) {
                $resp->getBody()->write(Json::resource("commande", $commande->toArray()));
            } else {
                $resp = $resp->withStatus(401);
                $resp->getBody()->write(Json::error(401, "Token incorrect"));
            }

        } else {
            $resp = $resp->withStatus(404);
            $resp->getBody()->write(Json::error(404, "ressource non disponible"));
        }
        return $resp;
    }

    public function createCommand(Request $req, Response $resp, $args)
    {
        header("Access-Control-Allow-Origin: *");
        if ($req->getAttribute('has_errors')) {
            $errors = $req->getAttribute('errors');
            var_dump($errors);
            foreach ($errors as $key => $listerrorAttribute) {
                echo "<strong>" . $key . " : </strong><br/>";
                //echo "<br/>";
                foreach ($listerrorAttribute as $error) {
                    echo $error;
                    echo "<br/>";
                }
            }
        } else {
            $resp = $resp->withHeader('Content-Type', 'application/json');
            $req_body = $req->getBody()->getContents();


            if (Json::isJson($req_body)) {
                $body = json_decode($req_body, true);
                $resp = $resp->withStatus(500);
                try {
                    $uuid = Uuid::uuid1();
                } catch (\Exception $e) {
                    echo $e;
                }
                $commande = new Commande();
                $commande->id = $uuid->toString();
                $commande->nom = filter_var($body["nom"], FILTER_SANITIZE_STRING);
                $commande->mail = filter_var($body["mail"], FILTER_SANITIZE_STRING);
                $commande->token = bin2hex(openssl_random_pseudo_bytes(32));
                $commande->montant = 0;
                $commande->livraison = $body["livraison"]["date"] . " " . $body["livraison"]["heure"];

                if (isset($body["client_id"])) {
                    $client = Client::find($body["client_id"]);
                    if ($client) {
                        $token = explode(" ", $req->getHeader("Authorization")[0])[1];
                        $tokenDecoded = JWT::decode($token, "lul", array('HS512'));

                        if ($client->id == $tokenDecoded->id)
                            $commande->client_id = $body["client_id"];
                    }
                }
                $total = 0;
                foreach ($body["items"] as $item) {
                    $total += $commande->addItem($item);
                    if (isset($body["client_id"])) {
                        $client->cumul_achats += $total;
                        $client->save();
                    }
                }
                $commande->save();

                $resp->getBody()->write(Json::resource("commande", $commande->toArray()));

                $resp = $resp->withHeader("Location", "http://api.commande.local:19080/commands/" . $uuid->toString());
                $resp = $resp->withStatus(201);
            } else {
                $resp->getBody()->write(Json::error(500, "merci de transmettre du JSON valide"));
            }
        }
        return $resp->withHeader('Access-Control-Allow-Origin', 'http://api.commande.local');
    }

    public function updateCommand(Request $req, Response $resp, $args)
    {
        $resp = $resp->withHeader('Content-Type', 'application/json');

        //Retourne une erreur 500 par défaut
        $resp = $resp->withStatus(500);

        $req_body = $req->getBody()->getContents();
        if (Json::isJson($req_body)) {
            $body = json_decode($req_body, true);

            if (isset($args["id"])) {
                $commande = Commande::find($args["id"]);

                if ($commande) {
                    $commande->livraison = htmlspecialchars($body["livraison"]);
                    $commande->nom = htmlspecialchars($body["nom"]);
                    $commande->mail = htmlspecialchars($body["mail"]);
                    $commande->montant = htmlspecialchars($body["montant"]);
                    $commande->remise = htmlspecialchars($body["remise"]);
                    $commande->token = htmlspecialchars($body["token"]);
                    $commande->client_id = htmlspecialchars($body["client_id"]);
                    $commande->ref_paiement = htmlspecialchars($body["ref_paiement"]);
                    $commande->date_paiement = htmlspecialchars($body["date_paiement"]);
                    $commande->mode_paiement = htmlspecialchars($body["mode_paiement"]);
                    $commande->status = htmlspecialchars($body["status"]);
                    $commande->save();
                    $resp = $resp->withStatus(200);
                    $resp->getBody()->write(Json::resource("commande", $commande->toArray()));
                } else {
                    $resp->getBody()->write(Json::error(404, "Commande introuvable"));
                }
            } else {
                $resp->getBody()->write(Json::error(500, "Des données sont manquantes"));
            }
        }
        return $resp;
    }
}
<?php

namespace DmServer\Controllers\Status;

use DmServer\Controllers\AbstractController;
use DmServer\DatabaseCheckHelper;
use DmServer\DmServer;
use DmServer\SimilarImagesHelper;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AppController extends AbstractController
{
    /**
     * @param $routing ControllerCollection
     */
    public static function addRoutes($routing)
    {
        $routing->get(
            '/status',
            /**
             * @codeCoverageIgnore
             */
            function (Application $app, Request $request) {
                return AbstractController::return500ErrorOnException($app, null, function () use ($app) {
                    $errors = [];
                    $output = [];
                    self::setClientVersion($app, '1.0.0');

                    $databaseChecks = [
                        [
                            'db' => DmServer::CONFIG_DB_KEY_DM,
                            'query' => 'SELECT * FROM users LIMIT 1'
                        ],
                        [
                            'db' => DmServer::CONFIG_DB_KEY_COA,
                            'query' => 'SELECT * FROM inducks_countryname LIMIT 1'
                        ],
                        [
                            'db' => DmServer::CONFIG_DB_KEY_COVER_ID,
                            'query' => 'SELECT ID, issuecode, url FROM covers LIMIT 1'
                        ],
                        [
                            'db' => DmServer::CONFIG_DB_KEY_DM_STATS,
                            'query' => 'SELECT * FROM utilisateurs_histoires_manquantes LIMIT 1'
                        ],
                        [
                            'db' => DmServer::CONFIG_DB_KEY_EDGECREATOR,
                            'query' => 'SELECT * FROM edgecreator_modeles2 LIMIT 1'
                        ]
                    ];

                    foreach ($databaseChecks as $dbCheck) {
                        $response = DatabaseCheckHelper::checkDatabase($app, $dbCheck['query'], $dbCheck['db']);
                        if ($response->getStatusCode() !== Response::HTTP_OK) {
                            $errors[] = $response->getContent();
                        }
                    }

                    if (count($errors) === 0) {
                        $output[] = 'OK for all databases';
                    }

                    try {
                        $pastecIndexesImagesNumber = SimilarImagesHelper::getIndexedImagesNumber();
                        if ($pastecIndexesImagesNumber > 0) {
                            $output[] = "Pastec OK with $pastecIndexesImagesNumber images indexed";
                        }
                        else {
                            $errors[] = "Pastec has no images indexed";
                        }
                    }
                    catch(\Exception $e) {
                        $errors[] = $e->getMessage();
                    }

                    if (count($errors) > 0) {
                        return new Response(implode('<br />', $errors));
                    } else {
                        return new Response(implode('<br />', $output));
                    }
                });

            }
        );
    }
}

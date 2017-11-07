<?php

namespace DmServer\Controllers\Ducksmanager;

use DmServer\Controllers\AbstractController;
use DmServer\CsvHelper;
use DmServer\DmServer;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use DDesrosiers\SilexAnnotations\Annotations as SLX;
use Radebatz\Silex2Swagger\Swagger\Annotations as S2S;
use Swagger\Annotations as SWG;

/**
 * @S2S\Controller(prefix="/ducksmanager",
 *   @SWG\Parameter(
 *     name="x-dm-version",
 *     in="header",
 *     required=true
 *   ),
 *   @SWG\Response(response=200),
 *   @SWG\Response(response="default", description="Error")
 * ),
 * @SLX\Before("DmServer\RequestInterceptor::checkVersion")
 */
class AppController extends AbstractController
{
    /**
     * @SLX\Route(
     *   @SLX\Request(method="POST", uri="user/new"),
     *   @SWG\Parameter(
     *     name="username",
     *     in="query",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     name="password",
     *     in="query",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     name="password2",
     *     in="query",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     name="email",
     *     in="query",
     *     required=true
     *   )
     * )
     * @param Application $app
     * @param Request $request
     * @return Response
     */
    public function createUser(Application $app, Request $request) {
        $check = self::callInternal($app, '/ducksmanager/new/check', 'GET', [
            $request->request->get('username'),
            $request->request->get('password'),
            $request->request->get('password2')
        ]);
        if ($check->getStatusCode() !== Response::HTTP_OK) {
            return $check;
        } else {
            return self::callInternal($app, '/ducksmanager/new', 'PUT', [
                'username' => $request->request->get('username'),
                'password' => $request->request->get('password'),
                'email' => $request->request->get('email')
            ]);
        }
    }

    /**
     * @SLX\Route(
     *   @SLX\Request(method="POST", uri="resetDemo")
     * )
     * @param Application $app
     * @return Response
     */
    public function resetDemo(Application $app) {
        $demoUserResponse = self::callInternal($app, '/rawsql', 'POST', [
            'query' => 'SELECT ID FROM users WHERE username=\'demo\'',
            'db' => DmServer::CONFIG_DB_KEY_DM
        ]);

        if ($demoUserResponse->getStatusCode() === Response::HTTP_OK) {
            $demoUserData = json_decode($demoUserResponse->getContent());
            if (!is_null($demoUserData) && count($demoUserData) > 0) {
                $demoUserId = $demoUserData[0]->ID;
                $dataDeleteResponse = self::callInternal($app, '/ducksmanager/' . $demoUserId . '/data', 'DELETE');
                if ($dataDeleteResponse->getStatusCode() === Response::HTTP_OK) {
                    $bookcaseOptionsResetResponse = self::callInternal($app, '/ducksmanager/' . $demoUserId . '/data/bookcase/reset', 'POST');

                    if ($bookcaseOptionsResetResponse->getStatusCode() === Response::HTTP_OK) {
                        self::setSessionUser($app, 'demo', $demoUserId);

                        $demoUserIssueData = CsvHelper::readCsv(implode(DIRECTORY_SEPARATOR, [getcwd(), 'assets', 'demo_user', 'issues.csv']));

                        foreach ($demoUserIssueData as $publicationData) {
                            $response = self::callInternal($app, '/collection/issues', 'POST', $publicationData);

                            if ($response->getStatusCode() !== Response::HTTP_OK) {
                                return $response;
                            }
                        }

                        $demoUserPurchaseData = CsvHelper::readCsv(implode(DIRECTORY_SEPARATOR, [getcwd(), 'assets', 'demo_user', 'purchases.csv']));

                        foreach ($demoUserPurchaseData as $purchaseData) {
                            $response = self::callInternal($app, '/collection/purchases', 'POST', $purchaseData);

                            if ($response->getStatusCode() !== Response::HTTP_OK) {
                                return $response;
                            }
                        }
                    }
                    return $bookcaseOptionsResetResponse;
                } else {
                    return $dataDeleteResponse;
                }
            }
            else {
                return new Response('Malformed demo user data or no data user', Response::HTTP_EXPECTATION_FAILED);
            }
        }
        else {
            return $demoUserResponse;
        }
    }
}
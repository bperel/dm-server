<?php
namespace App\Tests\Controller;

use App\Entity\Dm\NumerosPopularite;
use App\Entity\Dm\TranchesPretes;
use App\Entity\Dm\UsersContributions;
use App\Entity\EdgeCreator\EdgecreatorIntervalles;
use App\Entity\EdgeCreator\EdgecreatorModeles2;
use App\Entity\EdgeCreator\EdgecreatorValeurs;
use App\Entity\EdgeCreator\ImagesMyfonts;
use App\Entity\EdgeCreator\ImagesTranches;
use App\Entity\EdgeCreator\TranchesEnCoursContributeurs;
use App\Entity\EdgeCreator\TranchesEnCoursModeles;
use App\Entity\EdgeCreator\TranchesEnCoursModelesImages;
use App\Entity\EdgeCreator\TranchesEnCoursValeurs;
use App\Tests\Fixtures\EdgeCreatorFixture;
use App\Tests\TestCommon;
use Countable;
use stdClass;
use Swift_Message;
use Symfony\Bundle\SwiftmailerBundle\DataCollector\MessageDataCollector;
use Symfony\Component\HttpFoundation\Response;

class EdgeCreatorTest extends TestCommon
{
    protected function getEmNamesToCreate() : array {
        return ['dm', 'edgecreator'];
    }

    /**
     * @param string $countryCode
     * @param string $publicationCode
     * @param string $issueCode
     * @return TranchesEnCoursModeles|object
     */
    private function getV2Model(string $countryCode, string $publicationCode, string $issueCode)
    {
        return $this->getEm('edgecreator')->getRepository(TranchesEnCoursModeles::class)->findOneBy([
            'pays' => $countryCode,
            'magazine' => $publicationCode,
            'numero' => $issueCode,
        ]);
    }

    public function setUp() : void
    {
        parent::setUp();
        $this->createUserCollection(self::$defaultTestDmUserName);

        EdgeCreatorFixture::$user = $this->getUser(self::$defaultTestDmUserName);
        $this->loadFixtures([ EdgeCreatorFixture::class ], true, 'edgecreator');
    }

    public function testCreateV2Model(): void
    {
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/v2/model/fr/DDD/10/1',
            self::$edgecreatorUser, 'PUT'
        )->call();

        $createdModel = $this->getEm('edgecreator')->getRepository(TranchesEnCoursModeles::class)->findOneBy([
            'pays' => 'fr',
            'magazine' => 'DDD',
            'numero' => '10',
            'username' => self::$defaultTestDmUserName
        ]);

        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals($createdModel->getId(), $objectResponse->modelid);
    }

    public function testCreateV2ModelNoUser(): void
    {
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/v2/model/fr/DDD/10/0',
            self::$edgecreatorUser, 'PUT'
        )->call();

        $createdModel = $this->getEm('edgecreator')->getRepository(TranchesEnCoursModeles::class)->findOneBy([
            'pays' => 'fr',
            'magazine' => 'DDD',
            'numero' => '10',
            'username' => null
        ]);

        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals($createdModel->getId(), $objectResponse->modelid);
    }

    public function testCreateV2ModelAlreadyExisting(): void
    {
        $this->buildAuthenticatedServiceWithTestUser('/edgecreator/v2/model/fr/DDD/10/1',
            self::$edgecreatorUser, 'PUT'
        )->call();

        // Another time
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/v2/model/fr/DDD/10/1',
            self::$edgecreatorUser, 'PUT'
        )->call();

        $this->assertUnsuccessfulResponse($response, function(Response $response) {
            $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
            $this->assertStringContainsString('UNIQUE constraint failed', $response->getContent());
        });
    }

    public function testLoadV2Model(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/v2/model/{$model->getId()}", self::$edgecreatorUser)->call();

        $responseModel = json_decode($this->getResponseContent($response));

        $this->assertEquals(json_decode(json_encode([
            'id' => 1,
            'pays' => 'fr',
            'magazine' => 'PM',
            'numero' => '502',
            'username' => self::$defaultTestDmUserName,
            'active' => true,
            'contributeurs' => [],
            'photos' => []
        ])), $responseModel);
    }

    public function testLoadV2UserModels(): void
    {
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/v2/model', self::$edgecreatorUser)->call();

        $responseObjects = json_decode($this->getResponseContent($response));

        $this->assertCount(1, $responseObjects);
        $this->assertEquals('1', $responseObjects[0]->est_editeur);
    }

    public function testGetModel(): void
    {
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/v2/model/fr/PM/502', self::$edgecreatorUser)->call();

        $model = json_decode($this->getResponseContent($response));
        $this->assertEquals('fr', $model->pays);
        $this->assertEquals('PM', $model->magazine);
        $this->assertEquals('502', $model->numero);
    }

    public function testGetModelNotExisting(): void
    {
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/v2/model/fr/PM/505', self::$edgecreatorUser)->call();

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testCreateStepWithOptionValue(): void
    {
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/step/fr/PM/1', self::$edgecreatorUser, 'PUT', [
            'functionname' => 'TexteMyFonts',
            'optionname' => 'Chaine',
            'optionvalue' => 'hello',
            'firstissuenumber' => '1',
            'lastissuenumber' => '2'
        ])->call();

        $objectResponse = json_decode($this->getResponseContent($response));

        $createdModel = $this->getEm('edgecreator')->getRepository(EdgecreatorModeles2::class)->findOneBy([
            'pays' => 'fr',
            'magazine' => 'PM',
            'ordre' => '1'
        ]);

        $this->assertEquals($createdModel->getId(), $objectResponse->optionid);

        $createdValue = $this->getEm('edgecreator')->getRepository(EdgecreatorValeurs::class)->findOneBy([
            'optionValeur' => 'hello'
        ]);

        $this->assertEquals($createdValue->getId(), $objectResponse->valueid);

        $createdInterval = $this->getEm('edgecreator')->getRepository(EdgecreatorIntervalles::class)->findOneBy([
            'idValeur' => $createdValue->getId()
        ]);

        $this->assertEquals($createdInterval->getId(), $objectResponse->intervalid);
    }

//    public function testCreateStepWithOptionValueExistingInterval()
//    {
//        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/step/fr/DDD/1',
//            self::$edgecreatorUser, 'PUT', [
//                'functionname' => 'Remplir',
//                'optionname' => 'Couleur',
//                'optionvalue' => '#FF0000',
//                'firstissuenumber' => '1',
//                'lastissuenumber' => '3'
//            ])->call();
//
//        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
//    }

    public function testCloneStep(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/v2/step/clone/{$model->getId()}/1/to/2", self::$edgecreatorUser, 'POST')->call();

        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals([json_decode(json_encode([
            ['old' => 1, 'new' => 2]
        ]))], $objectResponse->newStepNumbers);
    }

    public function testCloneStepNothingToClone(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/v2/step/clone/{$model->getId()}/3/to/4", self::$edgecreatorUser, 'POST')->call();

        $this->assertUnsuccessfulResponse($response, function(Response $response) {
            $this->assertEquals('No values to clone for '.json_encode([
                'idModele' => 1,
                'ordre' => 3
            ]), $response->getContent());
        });
    }

    public function testUpdateNonExistingStep(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/v2/step/{$model->getId()}/3", self::$edgecreatorUser, 'POST', [
            'options' => [
                'Couleur' => '#DDDDDD',
                'Pos_x' => '1',
                'Pos_y' => '2'
            ]
        ])->call();

        $this->assertUnsuccessfulResponse($response, function(Response $response) {
            $this->assertEquals('No option exists for this step and no function name was provided', $response->getContent());
        });
    }

    public function testUpdateStepWithInvalidOptions(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/v2/step/{$model->getId()}/3", self::$edgecreatorUser, 'POST', [
            'stepfunctionname' => 'Remplir',
            'options' => '2'
        ])->call();

        $this->assertUnsuccessfulResponse($response, function(Response $response) {
            $this->assertEquals('Invalid options input : 2', $response->getContent());
        });
    }

    public function testUpdateStepWithEmptyOptions(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/v2/step/{$model->getId()}/3", self::$edgecreatorUser, 'POST', [
            'stepfunctionname' => 'Remplir',
            'options!!!' => []
        ])->call();

        $this->assertUnsuccessfulResponse($response, function(Response $response) {
            $this->assertEquals('No options provided, ignoring step 3', $response->getContent());
        });
    }

    public function testUpdateStep(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/v2/step/{$model->getId()}/1", self::$edgecreatorUser, 'POST', [
            'stepfunctionname' => 'Remplir',
            'options' => [
                'Couleur' => '#DDDDDD',
                'Pos_x' => '1',
                'Pos_y' => '2'
            ]
        ])->call();

        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals(
            [
                [
                    'name' => 'Couleur',
                    'value' => '#DDDDDD'
                ],[
                    'name' => 'Pos_x',
                    'value' => '1'
                ],[
                    'name' => 'Pos_y',
                    'value' => '2'
                ]
            ],
            json_decode(json_encode($objectResponse->valueids), true)
        );
        /** @var TranchesEnCoursValeurs[] $values */
        $values = $this->getEm('edgecreator')->getRepository(TranchesEnCoursValeurs::class)->findBy([
            'idModele' => $model->getId(),
            'ordre' => 2
        ]);

        // Unchanged
        $this->assertEquals(2, $values[0]->getOrdre());
        $this->assertEquals('Couleur_texte', $values[0]->getOptionNom());
        $this->assertEquals('#000000', $values[0]->getOptionValeur());
    }

    public function testInsertStepNegativeStepNumber(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/v2/step/{$model->getId()}/-1", self::$edgecreatorUser, 'POST', [
            'stepfunctionname' => 'Remplir',
            'options' => [
                'Couleur' => '#AAAAAA',
                'Pos_x' => '5',
                'Pos_y' => '10'
            ]
        ])->call();

        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals(
            [
                [
                    'name' => 'Couleur',
                    'value' => '#AAAAAA'
                ],[
                'name' => 'Pos_x',
                'value' => '5'
            ],[
                'name' => 'Pos_y',
                'value' => '10'
            ]
            ],
            json_decode(json_encode($objectResponse->valueids), true)
        );
        /** @var TranchesEnCoursValeurs[]|Countable $values */
        $values = $this->getEm('edgecreator')->getRepository(TranchesEnCoursValeurs::class)->findBy([
            'idModele' => $model->getId(),
            'ordre' => -1
        ]);

        $this->assertCount(3, $values);
        $this->assertEquals('Couleur', $values[0]->getOptionNom());
        $this->assertEquals('#AAAAAA', $values[0]->getOptionValeur());

        $this->assertEquals('Pos_x', $values[1]->getOptionNom());
        $this->assertEquals('5', $values[1]->getOptionValeur());

        $this->assertEquals('Pos_y', $values[2]->getOptionNom());
        $this->assertEquals('10', $values[2]->getOptionValeur());
    }

    public function testInsertStep(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/v2/step/{$model->getId()}/2", self::$edgecreatorUser, 'POST', [
            'stepfunctionname' => 'Remplir',
            'options' => [
                'Couleur' => '#AAAAAA',
                'Pos_x' => '5',
                'Pos_y' => '10'
            ]
        ])->call();

        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals(
            [
                [
                    'name' => 'Couleur',
                    'value' => '#AAAAAA'
                ],[
                    'name' => 'Pos_x',
                    'value' => '5'
                ],[
                    'name' => 'Pos_y',
                    'value' => '10'
                ]
            ],
            json_decode(json_encode($objectResponse->valueids), true)
        );
        /** @var TranchesEnCoursValeurs[]|Countable $values */
        $values = $this->getEm('edgecreator')->getRepository(TranchesEnCoursValeurs::class)->findBy([
            'idModele' => $model->getId(),
            'ordre' => 2
        ]);

        $this->assertCount(3, $values);
        $this->assertEquals('Couleur', $values[0]->getOptionNom());
        $this->assertEquals('#AAAAAA', $values[0]->getOptionValeur());

        $this->assertEquals('Pos_x', $values[1]->getOptionNom());
        $this->assertEquals('5', $values[1]->getOptionValeur());

        $this->assertEquals('Pos_y', $values[2]->getOptionNom());
        $this->assertEquals('10', $values[2]->getOptionValeur());
    }

    public function testCloneModel(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');
        $model->setUsername(null); // Reset the assigned username to check that the clone service assigns it again
        $this->getEm('edgecreator')->persist($model);
        $this->getEm('edgecreator')->flush();

        $stepsToClone = [
            'steps' => [
                '1' => [
                    'stepfunctionname' => 'Remplir',
                    'options' => [
                        'Couleur' => '#CCCCCC',
                        'Pos_x' => '15',
                        'Pos_y' => '20'
                    ]
                ],
                '2' => [
                    'stepfunctionname' => 'Remplir',
                    'options' => [
                        'Couleur' => '#AAAAAA',
                        'Pos_x' => '5',
                        'Pos_y' => '10'
                    ]
                ]
            ]
        ];

        $expectedValueIds = [
            '1' => [
                [
                    'name' => 'Couleur',
                    'value' => '#CCCCCC'
                ],[
                    'name' => 'Pos_x',
                    'value' => '15'
                ],[
                    'name' => 'Pos_y',
                    'value' => '20'
                ]
            ],
            '2' => [
                [
                    'name' => 'Couleur',
                    'value' => '#AAAAAA'
                ],[
                    'name' => 'Pos_x',
                    'value' => '5'
                ],[
                    'name' => 'Pos_y',
                    'value' => '10'
                ]
            ]
        ];

        $assertValues = function($modelId) {

            $model = $this->getEm('edgecreator')->getRepository(TranchesEnCoursModeles::class)->find($modelId);
            $this->assertEquals(self::$defaultTestDmUserName, $model->getUsername());

            /** @var TranchesEnCoursValeurs[]|Countable $valuesStep1 */
            $valuesStep1 = $this->getEm('edgecreator')->getRepository(TranchesEnCoursValeurs::class)->findBy([
                'idModele' => $modelId,
                'ordre' => 1
            ]);

            $this->assertCount(3, $valuesStep1);
            $this->assertEquals('Couleur', $valuesStep1[0]->getOptionNom());
            $this->assertEquals('#CCCCCC', $valuesStep1[0]->getOptionValeur());

            $this->assertEquals('Pos_x', $valuesStep1[1]->getOptionNom());
            $this->assertEquals('15', $valuesStep1[1]->getOptionValeur());

            $this->assertEquals('Pos_y', $valuesStep1[2]->getOptionNom());
            $this->assertEquals('20', $valuesStep1[2]->getOptionValeur());

            /** @var TranchesEnCoursValeurs[]|Countable $valuesStep2 */
            $valuesStep2 = $this->getEm('edgecreator')->getRepository(TranchesEnCoursValeurs::class)->findBy([
                'idModele' => $modelId,
                'ordre' => 2
            ]);

            $this->assertCount(3, $valuesStep2);
            $this->assertEquals('Couleur', $valuesStep2[0]->getOptionNom());
            $this->assertEquals('#AAAAAA', $valuesStep2[0]->getOptionValeur());

            $this->assertEquals('Pos_x', $valuesStep2[1]->getOptionNom());
            $this->assertEquals('5', $valuesStep2[1]->getOptionValeur());

            $this->assertEquals('Pos_y', $valuesStep2[2]->getOptionNom());
            $this->assertEquals('10', $valuesStep2[2]->getOptionValeur());
        };

        // Existing model with steps
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/v2/model/clone/to/fr/PM/502', self::$edgecreatorUser, 'POST', $stepsToClone)->call();

        $this->getEm('edgecreator')->clear();
        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals(1, $objectResponse->modelid);
        $this->assertEquals(3, $objectResponse->deletedsteps);
        $this->assertEquals($expectedValueIds, json_decode(json_encode($objectResponse->valueids), true));

        $assertValues($objectResponse->modelid);


        // New model
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/v2/model/clone/to/fr/PM/505', self::$edgecreatorUser, 'POST', $stepsToClone)->call();

        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals(5, $objectResponse->modelid);
        $this->assertEquals(0, $objectResponse->deletedsteps);
        $this->assertEquals($expectedValueIds, json_decode(json_encode($objectResponse->valueids), true));

        $assertValues($objectResponse->modelid);
    }

    public function testShiftStep(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/v2/step/shift/{$model->getId()}/1/inclusive", self::$edgecreatorUser, 'POST')->call();
        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals(json_decode(json_encode([
            ['old' => 1, 'new' => 2],
            ['old' => 2, 'new' => 3]
        ])), $objectResponse->shifts);
    }

    public function testDeleteStep(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');
        $stepToRemove = 1;

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/v2/step/{$model->getId()}/$stepToRemove", self::$edgecreatorUser, 'DELETE')->call();
        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals([
            'removed' => [
                'model' => $model->getId(),
                'step' => $stepToRemove
            ]
        ], json_decode(json_encode($objectResponse), true));

        $values = $this->getEm('edgecreator')->getRepository(TranchesEnCoursValeurs::class)->findBy([
            'idModele' => $model->getId(),
            'ordre' => $stepToRemove
        ]);

        $this->assertCount(0, $values);
    }

    public function testCreateMyfontsPreview(): void
    {
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/myfontspreview', self::$edgecreatorUser, 'PUT', [
            'font' => 'Arial',
            'fgColor' => '#000000',
            'bgColor' => '#FFFFFF',
            'width' => 200,
            'text' => 'Hello preview',
            'precision' => 18,
        ])->call();

        $objectResponse = json_decode($this->getResponseContent($response));

        $createdPreview = $this->getEm('edgecreator')->getRepository(ImagesMyfonts::class)->findOneBy([
            'texte' => 'Hello preview'
        ]);

        $this->assertEquals($createdPreview->getId(), $objectResponse->previewid);
    }

    public function testDeleteMyFontsPreview(): void
    {
        $newPreview = new ImagesMyfonts();
        $this->getEm('edgecreator')->persist($newPreview);
        $this->getEm('edgecreator')->flush();

        $newPreviewId = $newPreview->getId();

        $this->buildAuthenticatedServiceWithTestUser("/edgecreator/myfontspreview/$newPreviewId", self::$edgecreatorUser, 'DELETE')->call();

        $this->getEm('edgecreator')->clear();
        $this->assertNull($this->getEm('edgecreator')->getRepository(ImagesMyfonts::class)->find($newPreviewId));
    }

    public function testDeactivateModel(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/model/v2/{$model->getId()}/deactivate", self::$edgecreatorUser, 'POST')
            ->call();

        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals($model->getId(), $objectResponse->deactivated);

        $this->getEm('edgecreator')->clear();
        $newModel = $this->getV2Model('fr', 'PM', '502');
        $this->assertEquals(false, $newModel->getActive());
    }

    public function testSetMainPhoto(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $this->assertSetMainPhotoOK($model, 'myphoto.jpg', 1);
    }

    public function testSetMainPhotoOtherContributorExisted(): void
    {
        $this->createUserCollection('otheruser');

        $model = $this->getV2Model('fr', 'PM', '502');
        $contributor = new TranchesEnCoursContributeurs();
        $contributor->setIdModele($model);
        $contributor->setIdUtilisateur($this->getUser('otheruser')->getId());
        $contributor->setContribution('photographe');
        $this->getEm('edgecreator')->persist($contributor);

        $this->getEm('edgecreator')->flush();

        $this->assertSetMainPhotoOK($model, 'myphoto.jpg', 2);
    }

    public function testSetMainPhotoSameContributorExisted(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');
        $contributor = new TranchesEnCoursContributeurs();
        $contributor->setIdModele($model);
        $contributor->setIdUtilisateur($this->getUser(self::$defaultTestDmUserName)->getId());
        $contributor->setContribution('photographe');
        $this->getEm('edgecreator')->persist($contributor);

        $this->getEm('edgecreator')->flush();

        $this->assertSetMainPhotoOK($model, 'myphoto.jpg', 1);
    }

    public function testSetMainPhotoPreviousExisted(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $photos = new ImagesTranches();
        $photos->setIdUtilisateur(1);
        $photos->setNomfichier('1.jpg');
        $this->getEm('edgecreator')->persist($photos);

        $edgePhoto = new TranchesEnCoursModelesImages();
        $edgePhoto->setIdModele($model);
        $edgePhoto->setIdImage($photos);
        $edgePhoto->setEstphotoprincipale(true);
        $this->getEm('edgecreator')->persist($edgePhoto);

        $this->getEm('edgecreator')->flush();

        $this->assertSetMainPhotoOK($model, 'myphoto.jpg', 1);
    }

    public function testGetMainPhoto(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $photo = new ImagesTranches();
        $photo->setIdUtilisateur($this->getUser(self::$defaultTestDmUserName)->getId());
        $photo->setNomfichier('abc.jpg');
        $this->getEm('edgecreator')->persist($photo);

        $modelPhoto = new TranchesEnCoursModelesImages();
        $modelPhoto->setEstphotoprincipale(true);
        $modelPhoto->setIdModele($model);
        $modelPhoto->setIdImage($photo);
        $this->getEm('edgecreator')->persist($modelPhoto);
        $this->getEm('edgecreator')->flush();

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/model/v2/{$model->getId()}/photo/main", self::$edgecreatorUser)->call();

        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals('abc.jpg', $objectResponse->nomfichier);
    }

    public function testGetMainPhotoNotExisting(): void
    {
        $model = $this->getV2Model('fr', 'PM', '502');

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/model/v2/{$model->getId()}/photo/main", self::$edgecreatorUser)->call();

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAddMultipleEdgePhoto(): void
    {
        self::$client->enableProfiler();

        $photoFileName = 'photo2.jpg';
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/multiple_edge_photo', self::$edgecreatorUser, 'PUT', [
            'hash' => sha1('test2'),
            'filename' => $photoFileName
        ])->call();

        $objectResponse = json_decode($this->getResponseContent($response));
        $this->assertEquals(['id' => 2 ], (array) $objectResponse->photo);

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = self::$client->getProfile()->getCollector('swiftmailer');
        /** @var Swift_Message[]|Countable $messages */
        $messages = $mailCollector->getMessages();

        $this->assertCount(1, $messages);
        [$message] = $messages;
        $this->assertStringContainsString("/tmp/uploaded_edges/$photoFileName", $message->getBody());
    }

    public function testAddMultipleEdgePhotoInvalidEmail(): void
    {
        $_ENV['SMTP_USERNAME'] = 'user@@ducksmanager.net';

        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/multiple_edge_photo', self::$edgecreatorUser, 'PUT', [
            'hash' => sha1('test2'),
            'filename' => 'photo2.jpg'
        ])->call();

        $this->assertUnsuccessfulResponse($response, function(Response $response) {
            $this->assertStringContainsString('does not comply with RFC', $response->getContent());
        });
    }

    public function testGetMultipleEdgePhotos(): void
    {
        $response = $this->buildAuthenticatedServiceWithTestUser('/edgecreator/multiple_edge_photo/today', self::$edgecreatorUser)->call();

        $photo = $this->getEm('edgecreator')->getRepository(ImagesTranches::class)->findOneBy([
            'hash' => sha1('test')
        ]);

        $objectResponse = json_decode($this->getResponseContent($response));
        $this->assertCount(1, $objectResponse);
        $photoResult = $objectResponse[0];
        $this->assertEquals($photo->getId(), $photoResult->id);
        $this->assertEquals($photo->getIdUtilisateur(), $photoResult->idUtilisateur);
        $this->assertEquals($photo->getHash(), $photoResult->hash);
        $this->assertEquals($photo->getDateheure()->getTimestamp(), $photoResult->dateheure->timestamp);
    }

    public function testGetMultipleEdgePhotoByHash(): void
    {

        $hash = sha1('test');
        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/multiple_edge_photo/hash/$hash", self::$edgecreatorUser)->call();

        $photo = $this->getEm('edgecreator')->getRepository(ImagesTranches::class)->findOneBy([
            'hash' => sha1('test')
        ]);

        $photoResult = json_decode($this->getResponseContent($response));
        $this->assertEquals($photo->getId(), $photoResult->id);
        $this->assertEquals($photo->getIdUtilisateur(), $photoResult->idUtilisateur);
        $this->assertEquals($photo->getHash(), $photoResult->hash);
        $this->assertEquals($photo->getDateheure()->getTimestamp(), $photoResult->dateheure->timestamp);
    }

    public function testGetElementImagesByNameSubstring(): void
    {
        EdgeCreatorFixture::createModelEcV1($this->getEm('edgecreator'), self::$edgecreatorUser, 'fr/MP', 1, 'Image', 'Source', 'MP.Tete.1.png', '1', '1');
        EdgeCreatorFixture::createModelEcV1($this->getEm('edgecreator'), self::$edgecreatorUser, 'fr/MP', 2, 'Image', 'Source', 'MP.Tete.[Numero].png', '1', '1');
        EdgeCreatorFixture::createModelEcV1($this->getEm('edgecreator'), self::$edgecreatorUser, 'fr/MP', 1, 'Image', 'Source', 'MP.Tete2.[Numero].png', '2', '2');

        EdgeCreatorFixture::createModelEcV2($this->getEm('edgecreator'), self::$edgecreatorUser, 'fr/PM', '1', [1 => ['functionName' => 'Image', 'options' => ['Source' => 'MP.Tete.1.png']]]);
        EdgeCreatorFixture::createModelEcV2($this->getEm('edgecreator'), self::$edgecreatorUser, 'fr/TP', '1', [2 => ['functionName' => 'Image', 'options' => ['Source' => 'MP.[Numero].png']]]);

        $name = 'MP.Tete.1.png';
        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/elements/images/$name", self::$edgecreatorUser)->call();

        $objectResponse = json_decode($this->getResponseContent($response));
        $this->assertEquals(
            ['MP.Tete.1.png', 'MP.Tete.[Numero].png', 'MP.Tete.1.png', 'MP.[Numero].png'],
            array_map(fn($element) => $element->Option_valeur, $objectResponse)
        );
    }

    public function testGetModelContributors() {
        EdgeCreatorFixture::createModelEcV2($this->getEm('edgecreator'), self::$edgecreatorUser, 'fr/PM', '1', [1 => ['functionName' => 'Image', 'options' => ['Source' => 'MP.Tete.1.png']]]);
        $model = $this->getEm('edgecreator')->getRepository(TranchesEnCoursModeles::class)->findOneBy(['pays' => 'fr', 'magazine' => 'PM', 'numero'=> '1']);

        $contributeur1 = new TranchesEnCoursContributeurs();
        $contributeur2 = new TranchesEnCoursContributeurs();
        $model->setContributeurs([
            $contributeur1
                ->setIdUtilisateur(1)
                ->setContribution('createur')
                ->setIdModele($model),
            $contributeur2
                ->setIdUtilisateur(3)
                ->setContribution('createur')
                ->setIdModele($model),
        ]);

        $this->getEm('edgecreator')->persist($model);
        $this->getEm('edgecreator')->flush();

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/contributors/{$model->getId()}", self::$edgecreatorUser)->call();
        $objectResponse = json_decode($this->getResponseContent($response));
        $this->assertEquals(1, $objectResponse[0]->idUtilisateur);
        $this->assertEquals('createur', $objectResponse[0]->contribution);
        $this->assertEquals(3, $objectResponse[1]->idUtilisateur);
        $this->assertEquals('createur', $objectResponse[1]->contribution);
    }

    public function testPublishEdge() {
        EdgeCreatorFixture::createModelEcV2(
            $this->getEm('edgecreator'),
            self::$edgecreatorUser,
            'fr/PM',
            '1',
            [1 => ['functionName' => 'Image', 'options' => ['Source' => 'MP.Tete.1.png']]]
        );
        $model = $this->getEm('edgecreator')->getRepository(TranchesEnCoursModeles::class)->findOneBy(
            ['pays' => 'fr', 'magazine' => 'PM', 'numero'=> '1']
        );
        $this->getEm('dm')->persist(
            (new NumerosPopularite())
                ->setPays('fr')
                ->setMagazine('PM')
                ->setNumero('1')
                ->setPopularite(5)
        );
        $this->getEm('dm')->flush();

        $contributeur1 = new TranchesEnCoursContributeurs();
        $contributeur2 = new TranchesEnCoursContributeurs();
        $model->setContributeurs([
            $contributeur1
                ->setIdUtilisateur(1)
                ->setContribution('createur')
                ->setIdModele($model),
            $contributeur2
                ->setIdUtilisateur(3)
                ->setContribution('createur')
                ->setIdModele($model),
        ]);

        $this->getEm('edgecreator')->persist($model);
        $this->getEm('edgecreator')->flush();

        $this->createUserCollection('otheruser');

        $designerUsernames = [self::$defaultTestDmUserName, self::$defaultTestDmUserName];
        $photographerUsernames = [self::$defaultTestDmUserName, 'otheruser'];

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/publish/{$model->getId()}", self::$edgecreatorUser, 'PUT', [
            'designers' => $designerUsernames,
            'photographers' => $photographerUsernames
        ])->call();
        $objectResponse = json_decode($this->getResponseContent($response));

        $publishedEdge = $this->getEm('dm')->getRepository(TranchesPretes::class)->findOneBy(['publicationcode' => 'fr/PM', 'issuenumber' => '1']);
        $this->assertNotNull($publishedEdge);
        $this->assertCount(3, $objectResponse->contributors);
        $this->assertEquals($publishedEdge->getId(), $objectResponse->edgeId);

        $userContributions = $this->getEm('dm')->getRepository(UsersContributions::class)->findBy([
            'tranche' => $publishedEdge
        ]);

        $this->assertEquals('photographe', $userContributions[0]->getContribution());
        $this->assertEquals($this->getUser(self::$defaultTestDmUserName), $userContributions[0]->getUser());
        $this->assertEquals(50 + 5, $userContributions[0]->getPointsTotal());

        $this->assertEquals('photographe', $userContributions[1]->getContribution());
        $this->assertEquals($this->getUser('otheruser'), $userContributions[1]->getUser());

        $this->assertEquals('createur', $userContributions[2]->getContribution());
        $this->assertEquals($this->getUser(self::$defaultTestDmUserName), $userContributions[2]->getUser());
    }

    private function assertSetMainPhotoOK(TranchesEnCoursModeles $model, string $photoName, int $expectedContributorNumber): void
    {
        $countryCode = $model->getPays();
        $publicationCode = $model->getMagazine();
        $issueCode = $model->getNumero();

        $response = $this->buildAuthenticatedServiceWithTestUser("/edgecreator/model/v2/{$model->getId()}/photo/main", self::$edgecreatorUser, 'PUT', [
            'photoname' => $photoName
        ])->call();

        $objectResponse = json_decode($this->getResponseContent($response));

        $this->assertEquals(['modelid' => $model->getId(), 'photoname' => $photoName], (array)$objectResponse->mainphoto);

        $newModel = $this->getV2Model($countryCode, $publicationCode, $issueCode);

        /** @var Countable|TranchesEnCoursModelesImages[] $photos */
        $photos = $this->getEm('edgecreator')->getRepository(TranchesEnCoursModelesImages::class)->findBy([
            'idModele' => $newModel
        ]);

        /** @var Countable|TranchesEnCoursContributeurs $helperUsers */
        $helperUsers = $this->getEm('edgecreator')->getRepository(TranchesEnCoursContributeurs::class)->findBy([
            'idModele' => $newModel
        ]);
        $this->assertCount(1, $photos);
        $this->assertEquals($photoName, $photos[0]->getIdImage()->getNomfichier());
        $this->assertCount($expectedContributorNumber, $helperUsers);
    }
}

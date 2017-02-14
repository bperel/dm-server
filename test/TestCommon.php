<?php
namespace DmServer\Test;

use Coa\Models\InducksCountryname;
use Coa\Models\InducksIssue;
use Coa\Models\InducksPerson;
use Coa\Models\InducksPublication;
use Coa\Models\InducksStory;
use CoverId\Models\Covers;
use DmServer\Controllers\AbstractController;
use DmStats\Models\AuteursHistoires;
use DmStats\Models\AuteursPseudosSimple;
use DmStats\Models\UtilisateursHistoiresManquantes;
use DmStats\Models\UtilisateursPublicationsManquantes;
use DmStats\Models\UtilisateursPublicationsSuggerees;
use Silex\Application;
use Silex\WebTestCase;
use Dm\Models\Numeros;
use Dm\Models\Users;
use DmServer\DmServer;

class TestCommon extends WebTestCase {

    protected static $conf;
    protected static $roles;
    protected static $users;
    protected static $defaultTestDmUserName = 'dm_test_user';
    protected static $testDmUsers = [
        'dm_test_user' => 'test'
    ];
    protected static $dmUser = 'dm_test';
    protected static $rawSqlUser = 'rawsql';
    protected static $adminUser = 'admin';
    protected static $uploadBase = '/tmp/dm-server';

    protected static $exampleImage = 'cover_example.jpg';

    /** @var SchemaWithClasses[] $schemas */
    private static $schemas = [];

    /** @var Application $app */
    protected $app;

    public static function setUpBeforeClass()
    {
        DmServer::initSettings('settings.test.ini');
        self::$conf = DmServer::getAppConfig(true);
        self::$roles = DmServer::getAppRoles(true);

        foreach(DmServer::$configuredEntityManagerNames as $emName) {
            self::$schemas[$emName] = SchemaWithClasses::createFromEntityManager(DmServer::getEntityManager($emName, true));
        }
    }

    public function setUp() {
        self::initDatabase();
        @rmdir(DmServer::$settings['image_local_root']);
        @mkdir(DmServer::$settings['image_local_root'], 0777, true);
        parent::setUp();
    }

    protected static function initDatabase() {

        foreach(DmServer::$configuredEntityManagerNames as $emName) {
            self::$schemas[$emName]->recreateSchema();
            DmServer::getEntityManager($emName)->clear();
        }
    }

    /**
     * @return Application
     */
    public function createApplication()
    {
        $app = null;
        $users = [];
        /** @noinspection PhpUnusedLocalVariableInspection */
        $conf = self::$conf;

        require __DIR__ . '/../index.php';

        self::$users = $users;
        return $app;
    }

    private static function getSystemCredentials($appUser, $version = '1.3+') {
        return self::getSystemCredentialsNoVersion($appUser) + [
            'HTTP_X_DM_VERSION' => $version
        ];
    }

    protected static function getSystemCredentialsNoVersion($appUser) {
        return [
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode(implode(':', [$appUser, explode(':', self::$roles[$appUser])[1]]))
        ];
    }

    /**
     * @param string $path
     * @param array $userCredentials
     * @param array $parameters
     * @param array $systemCredentials
     * @param string $method
     * @param array $files
     * @return TestServiceCallCommon
     */
    protected function buildService(
        $path, $userCredentials, $parameters = [], $systemCredentials = [], $method = 'POST', $files = []
    ) {
        $service = new TestServiceCallCommon(static::createClient());
        $service->setPath($path);
        $service->setUserCredentials($userCredentials);
        $service->setParameters($parameters);
        $service->setSystemCredentials($systemCredentials);
        $service->setMethod($method);
        $service->setFiles($files);
        return $service;
    }
    
    protected function buildAuthenticatedService($path, $appUser, $userCredentials, $parameters = [], $method = 'POST') {
        return $this->buildService($path, $userCredentials, $parameters, self::getSystemCredentials($appUser), $method, []);
    }

    protected function buildAuthenticatedServiceWithTestUser($path, $appUser, $method = 'GET', $parameters = [], $files = []) {
        return $this->buildService(
            $path, [
            'username' => self::$defaultTestDmUserName,
            'password' => sha1(self::$testDmUsers[self::$defaultTestDmUserName])
        ], $parameters, self::getSystemCredentials($appUser), $method, $files
        );
    }

    protected function getCurrentUserIssues() {
        $dmEntityManager = DmServer::$entityManagers[DmServer::CONFIG_DB_KEY_DM];
        return $dmEntityManager->getRepository(Numeros::class)->findBy(
            ['idUtilisateur' => AbstractController::getSessionUser($this->app)['id']]
        );
    }

    protected static function createUser($username, $password) {
        $dmEntityManager = DmServer::$entityManagers[DmServer::CONFIG_DB_KEY_DM];

        $user = new Users();
        $user->setBetauser(false);
        $user->setUsername($username);
        $user->setPassword(sha1($password));
        $user->setEmail('test@ducksmanager.net');
        $user->setDateinscription(\DateTime::createFromFormat('Y-m-d', '2000-01-01'));
        $dmEntityManager->persist($user);

        $dmEntityManager->flush();

        return $user;
    }

    /**
     * @param string $username
     * @return array user info
     */
    protected static function createTestCollection($username = 'dm_test_user') {
        $dmEntityManager = DmServer::$entityManagers[DmServer::CONFIG_DB_KEY_DM];

        $user = self::createUser($username, self::$testDmUsers[$username] ?? 'password');

        $numero1 = new Numeros();
        $numero1->setPays('fr');
        $numero1->setMagazine('DDD');
        $numero1->setNumero('1');
        $numero1->setEtat('indefini');
        $numero1->setIdAcquisition('-2');
        $numero1->setAv(false);
        $numero1->setIdUtilisateur($user->getId());
        $dmEntityManager->persist($numero1);
        $dmEntityManager->flush();

        $numero2 = new Numeros();
        $numero2->setPays('fr');
        $numero2->setMagazine('MP');
        $numero2->setNumero('300');
        $numero2->setEtat('bon');
        $numero2->setIdUtilisateur($user->getId());
        $dmEntityManager->persist($numero2);
        $dmEntityManager->flush();

        $numero3 = new Numeros();
        $numero3->setPays('fr');
        $numero3->setMagazine('MP');
        $numero3->setNumero('301');
        $numero3->setEtat('mauvais');
        $numero3->setIdUtilisateur($user->getId());
        $dmEntityManager->persist($numero3);

        $dmEntityManager->flush();
        $dmEntityManager->clear();

        return ['username' => $user->getUsername(), 'id' => $user->getId()];
    }

    protected static function createCoaData() {
        $coaEntityManager = DmServer::$entityManagers[DmServer::CONFIG_DB_KEY_COA];
        
        $country1 = new InducksCountryname();
        $country1->setCountrycode('fr');
        $country1->setLanguagecode('fr');
        $country1->setCountryname('France');
        $coaEntityManager->persist($country1);

        $country2 = new InducksCountryname();
        $country2->setCountrycode('es');
        $country2->setLanguagecode('fr');
        $country2->setCountryname('Espagne');
        $coaEntityManager->persist($country2);

        $publication1 = new InducksPublication();
        $publication1->setPublicationCode('fr/DDD');
        $publication1->setCountrycode('fr');
        $publication1->setTitle('Dynastie');
        $coaEntityManager->persist($publication1);

        $publication2 = new InducksPublication();
        $publication2->setPublicationCode('fr/MP');
        $publication2->setCountrycode('fr');
        $publication2->setTitle('Parade');
        $coaEntityManager->persist($publication2);

        $publication3 = new InducksPublication();
        $publication3->setPublicationCode('us/CBL');
        $publication3->setCountrycode('us');
        $publication3->setTitle('Carl Barks Library');
        $coaEntityManager->persist($publication3);

        $issue1 = new InducksIssue();
        $issue1->setPublicationcode('fr/DDD');
        $issue1->setIssuenumber('1');
        $issue1->setIssuecode('fr/DDD 1');
        $coaEntityManager->persist($issue1);

        $issue2 = new InducksIssue();
        $issue2->setPublicationcode('fr/DDD');
        $issue2->setIssuenumber('2');
        $issue2->setIssuecode('fr/DDD 2');
        $coaEntityManager->persist($issue2);

        $issue3 = new InducksIssue();
        $issue3->setPublicationcode('fr/MP');
        $issue3->setIssuenumber('300');
        $issue3->setIssuecode('fr/MP 300');
        $coaEntityManager->persist($issue3);

        $story1 = new InducksStory();
        $story1->setTitle('Title of story W WDC  31-05');
        $story1->setStorycomment('Comment of story W WDC  31-05');
        $story1->setStorycode('W WDC  31-05');
        $coaEntityManager->persist($story1);

        $story2 = new InducksStory();
        $story2->setTitle('Title of story W WDC  32-02');
        $story2->setStorycomment('Comment of story W WDC  32-02');
        $story2->setStorycode('W WDC  32-02');
        $coaEntityManager->persist($story2);

        $story3 = new InducksStory();
        $story3->setTitle('Title of story ARC CBL 5B');
        $story3->setStorycomment('Comment of story ARC CBL 5B');
        $story3->setStorycode('ARC CBL 5B');
        $coaEntityManager->persist($story3);

        $coaEntityManager->flush();
        $coaEntityManager->clear();
    }

    public function createStatsData() {
        $dmStatsEntityManager = DmServer::$entityManagers[DmServer::CONFIG_DB_KEY_DM_STATS];

        $userId = AbstractController::getSessionUser($this->app)['id'];

        $authorUser1 = new AuteursPseudosSimple();
        $authorUser1->setIdUser($userId);
        $authorUser1->setNomauteurabrege('CB');
        $dmStatsEntityManager->persist($authorUser1);

        $dmStatsEntityManager->flush();

        $author1Story1 = new AuteursHistoires();
        $author1Story1->setPersoncode('CB');
        $author1Story1->setStorycode('ARC CBL 5B');
        $dmStatsEntityManager->persist($author1Story1);

        $author1Story2 = new AuteursHistoires();
        $author1Story2->setPersoncode('CB');
        $author1Story2->setStorycode('W WDC  32-02');
        $dmStatsEntityManager->persist($author1Story2);

        $author1Story3 = new AuteursHistoires();
        $author1Story3->setPersoncode('CB');
        $author1Story3->setStorycode('W WDC  31-05');
        $dmStatsEntityManager->persist($author1Story3);

        $author2Story1 = new AuteursHistoires();
        $author2Story1->setPersoncode('DR');
        $author2Story1->setStorycode('AR 201');
        $dmStatsEntityManager->persist($author2Story1);

        $missingStory1ForUser = new UtilisateursHistoiresManquantes();
        $missingStory1ForUser->setPersoncode($author1Story1->getPersoncode());
        $missingStory1ForUser->setStorycode($author1Story1->getStorycode());
        $missingStory1ForUser->setIdUser($userId);
        $dmStatsEntityManager->persist($missingStory1ForUser);

        $missingStory2ForUser = new UtilisateursHistoiresManquantes();
        $missingStory2ForUser->setPersoncode($author1Story2->getPersoncode());
        $missingStory2ForUser->setStorycode($author1Story2->getStorycode());
        $missingStory2ForUser->setIdUser($userId);
        $dmStatsEntityManager->persist($missingStory2ForUser);

        $missingIssue1ofStory1ForUser = new UtilisateursPublicationsManquantes();
        $missingIssue1ofStory1ForUser->setPersoncode($author1Story1->getPersoncode());
        $missingIssue1ofStory1ForUser->setStorycode($author1Story1->getStorycode());
        $missingIssue1ofStory1ForUser->setIdUser($userId);
        $missingIssue1ofStory1ForUser->setPublicationcode('us/CBL');
        $missingIssue1ofStory1ForUser->setIssuenumber('7');
        $missingIssue1ofStory1ForUser->setNotation(2);
        $dmStatsEntityManager->persist($missingIssue1ofStory1ForUser);

        $missingIssue1ofStory2ForUser = new UtilisateursPublicationsManquantes();
        $missingIssue1ofStory2ForUser->setPersoncode($author1Story2->getPersoncode());
        $missingIssue1ofStory2ForUser->setStorycode($author1Story2->getStorycode());
        $missingIssue1ofStory2ForUser->setIdUser($userId);
        $missingIssue1ofStory2ForUser->setPublicationcode('us/CBL');
        $missingIssue1ofStory2ForUser->setIssuenumber('7');
        $missingIssue1ofStory2ForUser->setNotation(2);
        $dmStatsEntityManager->persist($missingIssue1ofStory2ForUser);

        $missingIssue2ofStory2ForUser = new UtilisateursPublicationsManquantes();
        $missingIssue2ofStory2ForUser->setPersoncode($author1Story2->getPersoncode());
        $missingIssue2ofStory2ForUser->setStorycode($author1Story2->getStorycode());
        $missingIssue2ofStory2ForUser->setIdUser($userId);
        $missingIssue2ofStory2ForUser->setPublicationcode('fr/DDD');
        $missingIssue2ofStory2ForUser->setIssuenumber('1');
        $missingIssue2ofStory2ForUser->setNotation(2);
        $dmStatsEntityManager->persist($missingIssue2ofStory2ForUser);

        $suggestedIssue1ForUser = new UtilisateursPublicationsSuggerees();
        $suggestedIssue1ForUser->setPublicationcode('us/CBL');
        $suggestedIssue1ForUser->setIssuenumber('7');
        $suggestedIssue1ForUser->setIdUser($userId);
        $suggestedIssue1ForUser->setScore(4);
        $dmStatsEntityManager->persist($suggestedIssue1ForUser);

        $suggestedIssue2ForUser = new UtilisateursPublicationsSuggerees();
        $suggestedIssue2ForUser->setPublicationcode('fr/DDD');
        $suggestedIssue2ForUser->setIssuenumber('1');
        $suggestedIssue2ForUser->setIdUser($userId);
        $suggestedIssue2ForUser->setScore(2);
        $dmStatsEntityManager->persist($suggestedIssue2ForUser);

        $dmStatsEntityManager->flush();
        $dmStatsEntityManager->clear();

        $coaEntityManager = DmServer::$entityManagers[DmServer::CONFIG_DB_KEY_COA];

        $inducksPerson = new InducksPerson();
        $inducksPerson->setPersoncode("CB");
        $inducksPerson->setFullname("Carl Barks");
        $coaEntityManager->persist($inducksPerson);

        $inducksPerson = new InducksPerson();
        $inducksPerson->setPersoncode("DR");
        $inducksPerson->setFullname("Don Rosa");
        $coaEntityManager->persist($inducksPerson);

        $coaEntityManager->flush();
        $coaEntityManager->clear();
    }

    protected static function createCoverIds()
    {
        $coverIdEntityManager = DmServer::$entityManagers[DmServer::CONFIG_DB_KEY_COVER_ID];

        $coverIds = [];

        $urls = [
            'fr/DDD 1' => 'webusers/2010/12/fr_ddd_001a_001.jpg',
            'fr/DDD 2' => 'webusers/2010/12/fr_ddd_002a_001.jpg',
            'fr/MP 300' => 'webusers/2010/12/fr_mp_0300a_001.jpg',
            'fr/XXX 111' => 'webusers/2010/12/fr_xxx_111_001.jpg'
        ];

        foreach($urls as $issueNumber => $url) {
            $cover = new Covers();
            $cover->setIssuecode($issueNumber);
            $cover->setUrl($url);
            $coverIdEntityManager->persist($cover);
            $coverIdEntityManager->flush();
            $coverIds[]= $cover->getId();

            @mkdir(DmServer::$settings['image_remote_root'].dirname($url), 0777, true);
            $imagePath = self::getPathToFileToUpload(self::$exampleImage);
            file_put_contents(DmServer::$settings['image_remote_root'] . $url, file_get_contents($imagePath));
        }

        return $coverIds;
    }

    protected static function getPathToFileToUpload($fileName) {
        return implode(DIRECTORY_SEPARATOR, [__DIR__, 'fixtures', $fileName]);
    }

    /**
     * @param Application $app
     * @param $userInfo array
     */
    protected static function setSessionUser(Application $app, $userInfo) {
        $app['session']->set('user', $userInfo);
    }
}
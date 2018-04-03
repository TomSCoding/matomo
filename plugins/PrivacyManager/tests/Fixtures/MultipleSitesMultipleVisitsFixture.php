<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\PrivacyManager\tests\Fixtures;

use Piwik\Common;
use Piwik\Date;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Piwik;
use Piwik\Tests\Framework\Fixture;
use Piwik\Plugins\Goals\API as ApiGoals;
use Piwik\Tracker\LogTable;


class TestLogFooBarBaz extends LogTable
{
    const TABLE = 'log_foo_bar_baz';

    public function install()
    {
        DbHelper::createTable($this->getName(), "
                  `idlogfoobarbaz` bigint(15) NOT NULL,
                  `idlogfoobar` bigint(15) NOT NULL");
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', Common::prefixTable($this->getName())));
    }

    public function insertEntry($idLogFooBarBaz, $idLogFooBar)
    {
        Db::query(sprintf('INSERT INTO %s VALUES(?,?)', Common::prefixTable($this->getName())), array($idLogFooBarBaz, $idLogFooBar));
    }

    public function getName()
    {
        return self::TABLE;
    }

    public function getIdColumn()
    {
        return 'idlogfoobarbaz';
    }

    public function getWaysToJoinTable()
    {
        return array('log_foo_bar' => 'idlogfoobar');
    }
}

class TestLogFooBar extends LogTable
{
    const TABLE = 'log_foo_bar';

    public function install()
    {
        DbHelper::createTable($this->getName(), "
                  `idlogfoobar` bigint(15) NOT NULL,
                  `idlogfoo` bigint(15) NOT NULL");
    }

    public function insertEntry($idLogFooBar, $idLogFoo)
    {
        Db::query(sprintf('INSERT INTO %s VALUES(?,?)', Common::prefixTable($this->getName())), array($idLogFooBar, $idLogFoo));
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', Common::prefixTable($this->getName())));
    }

    public function getName()
    {
        return self::TABLE;
    }

    public function getIdColumn()
    {
        return 'idlogfoobar';
    }

    public function getWaysToJoinTable()
    {
        return array('log_foo' => 'idlogfoo');
    }
}

class TestLogFoo extends LogTable
{
    const TABLE = 'log_foo';

    public function install()
    {
        DbHelper::createTable($this->getName(), "
                  `idlogfoo` bigint(15) NOT NULL,
                  `idsite` bigint(15) NOT NULL,
                  `idvisit` bigint(15) NOT NULL");
    }

    public function insertEntry($idLogFoo, $idSite, $idVisit)
    {
        Db::query(sprintf('INSERT INTO %s VALUES(?,?,?)', Common::prefixTable($this->getName())), array($idLogFoo, $idSite, $idVisit));
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', Common::prefixTable($this->getName())));
    }

    public function getColumnToJoinOnIdVisit()
    {
        return 'idvisit';
    }

    public function getName()
    {
        return self::TABLE;
    }

    public function getIdColumn()
    {
        return 'idlogfoo';
    }

}


class MultipleSitesMultipleVisitsFixture extends Fixture
{

    private static $countryCode = array(
        'CA', 'CN', 'DE', 'ES', 'FR', 'IE', 'IN', 'IT', 'MX', 'PT', 'RU', 'GB', 'US'
    );

    private static $generationTime = array(
        false, 1030, 545, 392, 9831, false, 348, 585, 984, 1249, false
    );

    private static $searchKeyword = array('piwik', 'analytics', 'web', 'mobile', 'ecommerce', 'custom');
    private static $searchCategory = array('', '', 'video', 'images', 'web', 'web');

    public $dateTime = '2017-01-02 03:04:05';
    public $trackingTime = '2017-01-02 03:04:05';
    public $idSite = 1;
    /**
     * @var \PiwikTracker
     */
    private $tracker;

    private $numSites = 5;
    private $currentUserId;

    public function setUp()
    {
        parent::setUp();
        $this->installLogTables();
        $this->setUpWebsites();
        $this->trackVisitsForMultipleSites();
    }

    public function tearDown()
    {
        // empty
    }

    public function installLogTables()
    {
        try {
            $columns = DbHelper::getTableColumns(TestLogFoo::TABLE);
            if (!empty($columns)) {
                return; // already installed
            }
        } catch (\Exception $e) {
            // not installed yet
        }
        $extraLogTables = array(new TestLogFooBar(), new TestLogFoo(), new TestLogFooBarBaz());
        foreach ($extraLogTables as $extraLogTable) {
            $extraLogTable->install();
        }

        Piwik::addAction('LogTables.addLogTables', function (&$logTables) use ($extraLogTables) {
            foreach ($extraLogTables as $extraLogTable) {
                $logTables[] = $extraLogTable;
            }
        });
    }

    public function uninstallLogTables()
    {
        $extraLogTables = array(new TestLogFooBar(), new TestLogFoo(), new TestLogFooBarBaz());
        foreach ($extraLogTables as $extraLogTable) {
            $extraLogTable->uninstall();
        }
    }

    private function trackVisitsForMultipleSites()
    {
        $this->trackVisits($idSite = 1, $numIterationsDifferentDays = 4);
        $this->trackVisits($idSite = 3, $numIterationsDifferentDays = 1);
        $this->trackVisits($idSite = 5, $numIterationsDifferentDays = 2);
    }

    public function setUpWebsites()
    {
        Fixture::createSuperUser(false);

        // we make sure by default nothing is anonymized
        $privacyConfig = new \Piwik\Plugins\PrivacyManager\Config();
        $privacyConfig->ipAddressMaskLength = 0;
        $privacyConfig->ipAnonymizerEnabled = false;

        for ($siteid = 1; $siteid <= $this->numSites; $siteid++) {
            if (!self::siteCreated($siteid)) {
                $idSite = self::createWebsite('2014-01-02 03:04:05', $ecommerce = 1, 'Site ' . $siteid);
                $this->assertSame($siteid, $idSite);

                $this->createGoals($idSite, 2);
            }
        }
    }

    public function createGoals($idSite, $numGoals)
    {
        $numGoals = range(1, $numGoals);

        $patterns = array(
            1 => '/path/1',
            2 => '/path/2',
        );
        $api = ApiGoals::getInstance();
        foreach ($numGoals as $idGoal) {
            if (!self::goalExists($idSite, $idGoal)) {
                $matchAttribute = 'url';
                $patternType = 'contains';
                $name = 'Goal ' . $idGoal;
                $caseSensitive = false;

                $pattern = 'fooBar';
                if (isset($patterns[$idGoal])) {
                    $pattern = $patterns[$idGoal];
                }

                $revenue = '0';

                $api->addGoal($idSite, $name, $matchAttribute, $pattern, $patternType, $caseSensitive, $revenue);
            }
        }
    }

    public function trackVisits($idSite, $numIterations)
    {
        if ($idSite == 1) {
            $this->installLogTables();
            $logFoo = new TestLogFoo();
            $logFoo->insertEntry(10, 1,1);
            $logFoo->insertEntry(21, 1,1);
            $logFoo->insertEntry(22, 1,2);

            $logFooBar = new TestLogFooBar();
            $logFooBar->insertEntry(35, 10);
            $logFooBar->insertEntry(36, 10);
            $logFooBar->insertEntry(37, 22);

            $logFooBar = new TestLogFooBarBaz();
            $logFooBar->insertEntry(51, 35);
            $logFooBar->insertEntry(52, 36);
        }

        for ($day = 0; $day < $numIterations; $day++) {
            // we track over several days to make sure we have some data to aggregate in week reports

            if ($day > 0) {
                $this->trackingTime = Date::factory($this->dateTime)->addDay($day * 3)->getDatetime();
            }

            $this->tracker = self::getTracker($idSite, $this->trackingTime, $defaultInit = true);
            $this->tracker->enableBulkTracking();
            $this->tracker->setUserAgent('Mozilla/5.0 (Windows NT 6.0; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.66 Safari/535.11');

            $this->trackVisit($userId = 200, 'http://www.example.com/', $idGoal = 1, $hoursAgo = null);
            $this->doTrack();

            $this->trackVisit($userId = 201, 'http://www.google.com?q=test', $idGoal = null, $hoursAgo = 2);
            $this->trackVisit($userId = 201, 'http://www.example.com/', $idGoal = 2);
            $this->doTrack();

            $this->trackVisit($userId = 202, null, $idGoal = null, $hoursAgo = 43);
            $this->trackVisit($userId = 202, 'http://www.google.com?q=test', $idGoal = null, $hoursAgo = 4);
            $this->trackVisit($userId = 202, 'http://www.example.com/', $idGoal = 1);
            $this->doTrack();

            $this->trackVisit($userId = 203, null, $idGoal = null, $hoursAgo = 43);
            $this->trackVisit($userId = 203, 'http://www.example.com', $idGoal = null, $hoursAgo = 18);
            $this->trackVisit($userId = 203, 'http://www.facebook.com/foo', $idGoal = null, $hoursAgo = 13);
            $this->trackVisit($userId = 203, null, $idGoal = null, $hoursAgo = 8);
            $this->trackVisit($userId = 203, 'http://www.matomo.org', $idGoal = null, $hoursAgo = 3);
            $this->trackVisit($userId = 203, 'http://www.google.com?q=test', $idGoal = null, $hoursAgo = 1);
            $this->trackVisit($userId = 203, 'http://www.innocraft.com', $idGoal = 1);

            $this->trackVisit($userId = 204, 'http://developer.matomo.org', $idGoal = 2);

            $this->trackVisit($userId = 205, 'http://www.matomo.org', $idGoal = null, $hoursAgo = 3);
            $this->trackVisit($userId = 205, null, $idGoal = 1);

            $this->trackVisit($userId = 206, 'http://ios.matomo.org', $idGoal = null, $hoursAgo = 2);
            $this->trackVisit($userId = 206, null, $idGoal = null, $hoursAgo = 5);
            $this->trackVisit($userId = 206, 'http://www.facebook.com/bar', $idGoal = null, $hoursAgo = 3);
            $this->trackVisit($userId = 206, null, $idGoal = 2);

            $this->trackVisit($userId = 207, 'http://hello.example.com', $idGoal = null, $hoursAgo = 3);
            $this->trackVisit($userId = 207, null, $idGoal = null, $hoursAgo = null);

            $this->trackVisit($userId = 208, 'http://example.matomo.org/mypath', $idGoal = null, $hoursAgo = 8);
            $this->trackVisit($userId = 208, null, $idGoal = null, $hoursAgo = null);
            $this->doTrack();

            $this->trackVisit($userId = 209, 'http://www.facebook.com/bar', $idGoal = null, $hoursAgo = 2);
            $this->trackVisit($userId = 209, null, $idGoal = 1);

            $this->trackVisit($userId = 210, null, $idGoal = 1, $hoursAgo = null);

            $this->trackVisit($userId = 211, 'http://developer.matomo.org?x=1', $idGoal = null, $hoursAgo = 1);
            $this->trackVisit($userId = 211, null, $idGoal = 2, $hoursAgo = 13);
            $this->trackVisit($userId = 211, null, $idGoal = 1, $hoursAgo = 10);
            $this->trackVisit($userId = 211, null, $idGoal = null, $hoursAgo = 4);
            $this->trackVisit($userId = 211, null, $idGoal = 1);
            $this->doTrack();
        }
    }

    private function doTrack()
    {
        self::checkBulkTrackingResponse($this->tracker->doBulkTrack());
    }

    private function initTracker($userId, $hoursAgo = null)
    {
        if (!empty($hoursAgo)) {
            $time = Date::factory($this->trackingTime)->subHour($hoursAgo)->getDatetime();
        } else {
            $time = $this->trackingTime;
        }
        $this->tracker->setForceNewVisit();
        $this->tracker->setIp('156.5.3.' . $userId);
        $this->tracker->setUserId('userId' . $userId);
        $this->tracker->setForceVisitDateTime($time);
        $this->tracker->setCustomVariable(1, 'myCustomUserId', $userId, 'visit');
        $this->tracker->setTokenAuth(Fixture::getTokenAuth());

        if (($userId % 10) < 9) {
            $this->tracker->setBrowserHasCookies(true);
        } else {
            $this->tracker->setBrowserHasCookies(false);
        }

        $numCountries = count(self::$countryCode);
        $this->tracker->setCountry(strtolower(self::$countryCode[$userId % $numCountries]));

        $numGenerationTimes = count(self::$generationTime);
        $this->tracker->setGenerationTime(strtolower(self::$generationTime[$userId % $numGenerationTimes]));
    }

    private function trackVisit($userId, $referrer, $idGoal = null, $hoursAgo = null)
    {
        $this->initTracker($userId, $hoursAgo);
        $this->tracker->setUrlReferrer($referrer);
        $this->tracker->setUrl('http://www.helloworld.com/hello/world' . $userId);
        $this->tracker->doTrackPageView('Hello World ');

        if (isset($idGoal)) {
            $this->tracker->doTrackGoal($idGoal);
        }

        $numAdditionalPageviews = $userId % 3;
        for ($j = 0; $j < $numAdditionalPageviews; $j++) {
            $trackingTime = Date::factory($this->trackingTime)->subHour($hoursAgo)->addHour(0.1)->getDatetime();
            $this->tracker->setForceVisitDateTime($trackingTime);
            $this->tracker->setUrl('http://www.helloworld.com/hello/world' . $userId . '/' . $j);
            $this->tracker->doTrackPageView('Hello World ' . $j);
        }

        if ($this->currentUserId === $userId) {
            return;
        }

        // we only want to do this once per user
        $this->currentUserId = $userId;

        $userIdHoursAgo = $userId + ($hoursAgo ? $hoursAgo : 1); // bring in more randomness

        if ($userId % 5 === 0) {
            $numKeywords = count(self::$searchKeyword);
            $keyword = strtolower(self::$searchKeyword[$userIdHoursAgo % $numKeywords]);

            $numCategories = count(self::$searchCategory);
            $category = strtolower(self::$searchCategory[$userIdHoursAgo % $numCategories]);

            $this->tracker->doTrackSiteSearch($keyword, $category, $userId);
        }

        if ($userId % 4 === 0) {
            $this->tracker->doTrackContentImpression('Product 1', '/path/product1.jpg', 'http://product1.example.com');
            $this->tracker->doTrackContentImpression('Product 1', 'Buy Product 1 Now!', 'http://product1.example.com');
            $this->tracker->doTrackContentImpression('Product 2', '/path/product2.jpg',  'http://product' . $userId . '.example.com');
            $this->tracker->doTrackContentImpression('Product 3', 'Product 3 on sale', 'http://product3.example.com');
            $this->tracker->doTrackContentImpression('Product 4');
            $this->tracker->doTrackContentInteraction('click', 'Product 3', 'Product 3 on sale', 'http://product3.example.com');
            $this->tracker->doTrackContentInteraction('hover', '/path/product1.jpg', 'http://product1.example.com');
        }

        if ($userId % 3 === 1) {
            $this->tracker->addEcommerceItem($sku = $userId, 'My product ' . $userId, 'Sound Category', $price = $userId, 1);

            if ($userId % 2 === 0) {
                $this->tracker->doTrackEcommerceCartUpdate(50);
            } else {
                $subtotal = $price * 1;
                $tax = $subtotal * 0.21;
                $shipping = $subtotal * 0.07;
                $discount = $subtotal * 0.14;
                $grandTotal = $subtotal + $shipping + $tax - $discount;
                $this->tracker->doTrackEcommerceOrder($userId, $grandTotal, $subtotal, $tax, $shipping, $discount);
            }
        }

        if ($userId % 3 === 0) {
            $this->tracker->doTrackEvent('Sound', 'play', 'Test Name', 2);
            $this->tracker->doTrackEvent('Sound', 'play', 'My Sound', 3);
            $this->tracker->doTrackEvent('Sound', 'stop', 'My Sound', 1);
            $this->tracker->doTrackEvent('Sound', 'resume', 'Another Sound');
            $this->tracker->doTrackEvent('Sound', 'play');
        }
    }

    public static function  cleanResult($result)
    {
        if (!empty($result) && is_array($result)) {
            foreach ($result as $key => $value) {
                if (is_array($value)) {
                    $result[$key] = self::cleanResult($value);
                } elseif ($key === 'idpageview') {
                    $result[$key] = '123456';
                }
            }
        }

        return $result;
    }


}
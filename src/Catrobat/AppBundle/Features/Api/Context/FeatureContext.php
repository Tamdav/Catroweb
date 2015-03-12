<?php

namespace Catrobat\AppBundle\Features\Api\Context;

use Behat\Behat\Tester\Exception\PendingException;
use Catrobat\AppBundle\Entity\RudeWord;
use Symfony\Component\HttpKernel\KernelInterface;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Gherkin\Node\PyStringNode, Behat\Gherkin\Node\TableNode;
use Catrobat\AppBundle\Entity\User;
use Catrobat\AppBundle\Entity\Program;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Behat\Behat\Context\CustomSnippetAcceptingContext;
use Catrobat\AppBundle\Services\TokenGenerator;
use Symfony\Bundle\FrameworkBundle\Client;
use Catrobat\AppBundle\Services\CatrobatFileCompressor;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Catrobat\AppBundle\Entity\FeaturedProgram;

//
// Require 3rd-party libraries here:
//
// require_once 'PHPUnit/Autoload.php';
require_once 'PHPUnit/Framework/Assert/Functions.php';
//

/**
 * Feature context.
 */
class FeatureContext implements KernelAwareContext, CustomSnippetAcceptingContext
{
  const FIXTUREDIR = "./testdata/DataFixtures/";
  private $error_directory;
  
  private $kernel;
  private $user;
  private $request_parameters;
  private $files;
  private $last_response;
  private $hostname;
  private $secure;
  
  /*
   * @var \Symfony\Component\HttpKernel\Client
   */
  private $client;
  
  public static function getAcceptedSnippetType()
  {
    return 'regex';
  }

  /**
   * Initializes context with parameters from behat.yml.
   *
   * @param array $parameters          
   */
  public function __construct($error_directory)
  {
    $this->error_directory = $error_directory;
    $this->request_parameters = array ();
    $this->files = array ();
    $this->hostname = "localhost";
    $this->secure = false;
  }

  /**
   * Sets HttpKernel instance.
   * This method will be automatically called by Symfony2Extension ContextInitializer.
   *
   * @param KernelInterface $kernel          
   */
  public function setKernel(KernelInterface $kernel)
  {
    $this->kernel = $kernel;
  }


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////// Support Functions

  private function emptyDirectory($directory)
  {
    if(!is_dir($directory))
    {
      return;
    }
    $filesystem = new Filesystem();

    $finder = new Finder();
    $finder->in($directory)->depth(0);
    foreach($finder as $file)
    {
      $filesystem->remove($file);
    }
  }

  private function prepareValidRegistrationParameters()
  {
    $this->request_parameters['registrationUsername'] = "newuser";
    $this->request_parameters['registrationPassword'] = "topsecret";
    $this->request_parameters['registrationEmail'] = "someuser@example.com";
    $this->request_parameters['registrationCountry'] = "at";
  }

  private function sendPostRequest($url)
  {
    $this->client = $this->kernel->getContainer()->get('test.client');
    $this->client->request('POST', $url, $this->request_parameters, $this->files);
  }
  
  private function uploadProgramFileAsDefaultUser($directory, $filename)
  {
    $filepath = $directory . "/" . $filename;
    assertTrue(file_exists($filepath), "File not found");
    $files = array(new UploadedFile($filepath, $filename));
    $url = "/api/upload/upload.json";
    $parameters = array(
        "username" => "BehatGeneratedName",
        "token" => "BehatGeneratedToken",
        "fileChecksum" => md5_file($files[0]->getPathname())
    );
    
    $this->client = $this->kernel->getContainer()->get('test.client');
    $this->client->request('POST', $url, $parameters, $files);
  }


  private function generateProgramFileWith($parameters)
  {
    $filesystem = new Filesystem();
    $this->emptyDirectory(sys_get_temp_dir()."/program_generated/");
    $new_program_dir = sys_get_temp_dir()."/program_generated/";
    $filesystem->mirror(self::FIXTUREDIR."/GeneratedFixtures/base", $new_program_dir);
    $properties = simplexml_load_file($new_program_dir."/code.xml");

    foreach($parameters as $name => $value) {

      switch ($name)
      {
        case "description":
          $properties->header->description = $value;
          break;
        case "name":
          $properties->header->programName = $value;
          break;
        case "platform":
          $properties->header->platform = $value;
          break;
        case "catrobatLanguageVersion":
          $properties->header->catrobatLanguageVersion = $value;
          break;
        case "applicationVersion":
          $properties->header->applicationVersion = $value;
          break;

        default:
          throw new PendingException("unknown xml field " . $name);
      }

    }


    $properties->asXML($new_program_dir."/code.xml");
    $compressor = new CatrobatFileCompressor();
    $compressor->compress($new_program_dir, sys_get_temp_dir()."/", "program_generated");

  }



////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////// Hooks

  /** @AfterSuite */
  protected function emptyDirectories()
  {
    $extract_dir = $this->kernel->getContainer()->getParameter("catrobat.file.storage.dir");
    $this->emptyDirectory($extract_dir);
    $extract_dir = $this->kernel->getContainer()->getParameter("catrobat.file.extract.dir");
    $this->emptyDirectory($extract_dir);
  }

  /** @BeforeScenario */
  public function emptyUploadFolder()
  {
    $extract_dir = $this->kernel->getContainer()->getParameter("catrobat.file.storage.dir");
    $this->emptyDirectory($extract_dir);
  }

  /** @BeforeScenario */
  public function emptyExtraxtFolder()
  {
    $extract_dir = $this->kernel->getContainer()->getParameter("catrobat.file.extract.dir");
    $this->emptyDirectory($extract_dir);
  }

  /** @AfterStep */
  public function saveResponseToFile(AfterStepScope $scope)
  {
    if (!$scope->getTestResult()->isPassed() && $this->client != null)
    {
      $response = $this->client->getResponse(); 
      if ($response->getContent() != "")
      {
        file_put_contents($this->error_directory . "error.json", $response->getContent());
      }
    }
  }

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////// Steps

  /**
   * @Given /^I have a program with "([^"]*)" as (name|description)$/
   */
  public function iHaveAProgramWithAsDescription($value, $header)
  {
    $this->generateProgramFileWith(array($header => $value));
  }
  
  /**
   * @When /^i upload this program$/
   */
  public function iUploadThisProgram()
  {
    $this->uploadProgramFileAsDefaultUser(sys_get_temp_dir(), "program_generated.catrobat");
  }
  
  /**
   * @Then /^the program should get (.*)$/
   */
  public function theProgramShouldGet($result)
  {
    $response = $this->client->getResponse();
    $response_array = json_decode($response->getContent(), true);
    $code = $response_array["statusCode"];
    switch ($result)
    {
      case "accepted":
        assertEquals(200, $code, "Program was rejected (Status code 200)");
        break;
      case "rejected":
        assertNotEquals(200, $code, "Program was NOT rejected");
        break;
      default:
        new PendingException();
    }
  }
  
  /**
   * @Given /^I define the following rude words:$/
   */
  public function iDefineTheFollowingRudeWords(TableNode $table)
  {
    $words = $table->getHash();

    $word = null;
    $em = $this->kernel->getContainer()->get('doctrine.orm.entity_manager');

    for($i = 0; $i < count($words); $i ++)
    {
      $word = new RudeWord();
      $word->setWord($words[$i]["word"]);
      $em->persist($word);
    }
    $em->flush();
  }

  /**
   * @Given /^I am a valid user$/
   */
  public function iAmAValidUser()
  {
    $user_manager = $this->kernel->getContainer()->get('usermanager');
    $user = $user_manager->createUser();
    $user->setUsername("BehatGeneratedName");
    $user->setEmail("dev@pocketcode.org");
    $user->setPlainPassword("BehatGeneratedPassword");
    $user->setEnabled(true);
    $user->setUploadToken("BehatGeneratedToken");
    $user->setCountry("at");
    $user_manager->updateUser($user, true);
  }

  /**
   * @When /^I upload a program with (.*)$/
   */
  public function iUploadAProgramWith($programattribute)
  {
    $filename = "NOFILENAMEDEFINED";
    switch($programattribute)
    {
      case "a rude word in the description":
        $filename = "program_with_rudeword_in_description.catrobat";
        break;
      case "a rude word in the name":
        $filename = "program_with_rudeword_in_name.catrobat";
        break;
      case "a missing code.xml":
        $filename = "program_with_missing_code_xml.catrobat";
        break;
      case "an invalid code.xml":
        $filename = "program_with_invalid_code_xml.catrobat";
        break;
      case "a missing image":
        $filename = "program_with_missing_image.catrobat";
        break;
      case "an additional image":
        $filename = "program_with_extra_image.catrobat";
        break;
        
      default:
        throw new PendingException("No case defined for \"" . $programattribute . "\"");
    }
    $this->uploadProgramFileAsDefaultUser(self::FIXTUREDIR . "/GeneratedFixtures", $filename);
  }

  /**
   * @When /^I upload an invalid program file$/
   */
  public function iUploadAnInvalidProgramFile()
  {
    $this->uploadProgramFileAsDefaultUser(self::FIXTUREDIR,"invalid_archive.catrobat");
  }
  
  /**
   * @Given /^there are users:$/
   */
  public function thereAreUsers(TableNode $table)
  {
    $user_manager = $this->kernel->getContainer()->get('usermanager');
    $users = $table->getHash();
    $user = null;
    for($i = 0; $i < count($users); $i ++)
    {
      $user = $user_manager->createUser();
      $user->setUsername($users[$i]["name"]);
      $user->setEmail("dev" . $i . "@pocketcode.org");
      $user->setPlainPassword($users[$i]["password"]);
      $user->setEnabled(true);
      $user->setUploadToken($users[$i]["token"]);
      $user->setCountry("at");
      $user_manager->updateUser($user, false);
    }
    $user_manager->updateUser($user, true);
  }

  /**
   * @Given /^there are programs:$/
   */
  public function thereArePrograms(TableNode $table)
  {
    $em = $this->kernel->getContainer()->get('doctrine')->getManager();
    $programs = $table->getHash();
    for($i = 0; $i < count($programs); $i ++)
    {
      $user = $em->getRepository('AppBundle:User')->findOneBy(array (
          'username' => $programs[$i]['owned by'] 
      ));
      $program = new Program();
      $program->setUser($user);
      $program->setName($programs[$i]['name']);
      $program->setDescription($programs[$i]['description']);
      $program->setFilename("file" . $i . ".catrobat");
      $program->setThumbnail("thumb.png");
      $program->setScreenshot("screenshot.png");
      $program->setViews($programs[$i]['views']);
      $program->setDownloads($programs[$i]['downloads']);
      $program->setUploadedAt(new \DateTime($programs[$i]['upload time'], new \DateTimeZone('UTC')));
      $program->setCatrobatVersion(1);
      $program->setCatrobatVersionName($programs[$i]['version']);
      $program->setLanguageVersion(1);
      $program->setUploadIp("127.0.0.1");
      $program->setRemixCount(0);
      $program->setFilesize(0);
      $program->setVisible(isset($programs[$i]['visible']) ? $programs[$i]['visible']=="true" : true);
      $program->setUploadLanguage("en");
      $program->setApproved(false);
      $em->persist($program);
    }
    $em->flush();
  }

  /**
   * @Given /^following programs are featured:$/
   */
  public function followingProgramsAreFeatured(TableNode $table)
  {
      $em = $this->kernel->getContainer()->get('doctrine')->getManager();
      $featured = $table->getHash();
      for($i = 0; $i < count($featured); $i ++)
      {
          $program = $em->getRepository('AppBundle:Program')->findOneByName($featured[$i]['name']);
          $featured_entry = new FeaturedProgram();
          $featured_entry->setProgram($program);
          $featured_entry->setActive($featured[$i]['active'] == 'yes');
          $featured_entry->setImageType("jpg");
          $em->persist($featured_entry);
      }
      $em->flush();
  }
  
  /**
   * @Given /^there are downloadable programs:$/
   */
  public function thereAreDownloadablePrograms(TableNode $table)
  {
    $em = $this->kernel->getContainer()->get('doctrine')->getManager();
    $file_repo = $this->kernel->getContainer()->get('filerepository');
    $programs = $table->getHash();
    for($i = 0; $i < count($programs); $i ++)
    {
      $user = $em->getRepository('AppBundle:User')->findOneBy(array (
          'username' => $programs[$i]['owned by'] 
      ));
      $program = new Program();
      $program->setUser($user);
      $program->setName($programs[$i]['name']);
      $program->setDescription($programs[$i]['description']);
      $program->setFilename("file" . $i . ".catrobat");
      $program->setThumbnail("thumb.png");
      $program->setScreenshot("screenshot.png");
      $program->setViews($programs[$i]['views']);
      $program->setDownloads($programs[$i]['downloads']);
      $program->setUploadedAt(new \DateTime($programs[$i]['upload time'], new \DateTimeZone('UTC')));
      $program->setCatrobatVersion(1);
      $program->setCatrobatVersionName($programs[$i]['version']);
      $program->setLanguageVersion(1);
      $program->setUploadIp("127.0.0.1");
      $program->setRemixCount(0);
      $program->setFilesize(0);
      $program->setVisible(isset($programs[$i]['visible']) ? $programs[$i]['visible']=="true" : true);
      $program->setUploadLanguage("en");
      $program->setApproved(false);
      $em->persist($program);
      $em->flush();
      $file_repo->saveProgramfile(new File(self::FIXTUREDIR . "test.catrobat"), $program->getId());
    }
  }
  
  /**
   * @Given /^I have a parameter "([^"]*)" with value "([^"]*)"$/
   */
  public function iHaveAParameterWithValue($name, $value)
  {
    $this->request_parameters[$name] = $value;
  }

  /**
   * @When /^I POST these parameters to "([^"]*)"$/
   */
  public function iPostTheseParametersTo($url)
  {
    $this->client = $this->kernel->getContainer()->get('test.client');
    $this->client->request('POST', $url, $this->request_parameters, $this->files);
  }

  /**
   * @When /^I GET "([^"]*)" with these parameters$/
   */
  public function iGetWithTheseParameters($url)
  {
    $this->client = $this->kernel->getContainer()->get('test.client');
    $this->client->request('GET', $url . "?" . http_build_query($this->request_parameters), array (), $this->files, array (
        'HTTP_HOST' => $this->hostname, 'HTTPS' => $this->secure
    ));
  }

  /**
   * @Given /^I am "([^"]*)"$/
   */
  public function iAm($username)
  {
    $this->user = $username;
  }

  /**
   * @Given /^I search for "([^"]*)"$/
   */
  public function iSearchFor($arg1)
  {
    $this->iHaveAParameterWithValue("q", $arg1);
    if (isset($this->request_parameters['limit']))
    {
      $this->iHaveAParameterWithValue("limit", $this->request_parameters['limit']);
    } else
    {
      $this->iHaveAParameterWithValue("limit", "1");
    }
    if (isset($this->request_parameters['offset']))
    {
      $this->iHaveAParameterWithValue("offset", $this->request_parameters['offset']);
    } else
    {
      $this->iHaveAParameterWithValue("offset", "0");
    }
    $this->iGetWithTheseParameters("/api/projects/search.json");
  }

  /**
   * @Then /^I should get a total of "([^"]*)" projects$/
   */
  public function iShouldGetATotalOfProjects($arg1)
  {
    $response = $this->client->getResponse();
    $responseArray = json_decode($response->getContent(), true);
    assertEquals($arg1, $responseArray['CatrobatInformation']['TotalProjects'], "Wrong number of total projects");
  }

  /**
   * @Given /^I use the limit "([^"]*)"$/
   */
  public function iUseTheLimit($arg1)
  {
    $this->iHaveAParameterWithValue("limit", $arg1);
  }

  /**
   * @Given /^I use the offset "([^"]*)"$/
   */
  public function iUseTheOffset($arg1)
  {
    $this->iHaveAParameterWithValue("offset", $arg1);
  }

  /**
   * @When /^I call "([^"]*)" with token "([^"]*)"$/
   */
  public function iCallWithToken($url, $token)
  {
    $this->client = $this->kernel->getContainer()->get('test.client');
    $params = array (
        "token" => $token,
        "username" => $this->user 
    );
    $this->client->request('GET', $url . "?" . http_build_query($params));
  }

  /**
   * @Then /^I should get the json object:$/
   */
  public function iShouldGetTheJsonObject(PyStringNode $string)
  {
    $response = $this->client->getResponse();
    assertJsonStringEqualsJsonString($string->getRaw(), $response->getContent(), "");
  }

  /**
   * @Then /^I should get the json object with random token:$/
   */
  public function iShouldGetTheJsonObjectWithRandomToken(PyStringNode $string)
  {
    $response = $this->client->getResponse();
    $responseArray = json_decode($response->getContent(), true);
    $expectedArray = json_decode($string->getRaw(), true);
    $responseArray['token'] = "";
    $expectedArray['token'] = "";
    assertEquals($expectedArray, $responseArray);
  }

  /**
   * @Then /^I should get the json object with random "([^"]*)" and "([^"]*)":$/
   */
  public function iShouldGetTheJsonObjectWithRandomAndProgramid($arg1, $arg2, PyStringNode $string)
  {
    $response = $this->client->getResponse();
    $responseArray = json_decode($response->getContent(), true);
    $expectedArray = json_decode($string->getRaw(), true);
    $responseArray[$arg1] = $expectedArray[$arg1] = "";
    $responseArray[$arg2] = $expectedArray[$arg2] = "";
    assertEquals($expectedArray, $responseArray, $response);
  }

  /**
   * @Then /^I should get programs in the following order:$/
   */
  public function iShouldGetProgramsInTheFollowingOrder(TableNode $table)
  {
    $response = $this->client->getResponse();
    $responseArray = json_decode($response->getContent(), true);
    $returned_programs = $responseArray['CatrobatProjects'];
    $expected_programs = $table->getHash();
    assertEquals(count($expected_programs), count($returned_programs), "Wrong number of returned programs");
    for($i = 0; $i < count($returned_programs); $i ++)
    {
      assertEquals($expected_programs[$i]["Name"], $returned_programs[$i]["ProjectName"], "Wrong order of results");
    }
  }

  /**
   * @Then /^I should get following programs:$/
   */
  public function iShouldGetFollowingPrograms(TableNode $table)
  {
    $this->iShouldGetProgramsInTheFollowingOrder($table);
  }

  /**
   * @Given /^the response code should be "([^"]*)"$/
   */
  public function theResponseCodeShouldBe($code)
  {
    $response = $this->client->getResponse();
    assertEquals($code, $response->getStatusCode(), "Wrong response code. " . $response->getContent());
  }

  /**
   * @Given /^the returned "([^"]*)" should be a number$/
   */
  public function theReturnedShouldBeANumber($arg1)
  {
    $response = json_decode($this->client->getResponse()->getContent(), true);
    assertTrue(is_numeric($response[$arg1]));
  }

  /**
   * @Given /^I have a file "([^"]*)"$/
   */
  public function iHaveAFile($filename)
  {
    $filepath = "./src/Catrobat/ApiBundle/Features/Fixtures/" . $filename;
    assertTrue(file_exists($filepath), "File not found");
    $this->files[] = new UploadedFile($filepath, $filename);
  }

  /**
   * @Given /^I have a valid Catrobat file$/
   */
  public function iHaveAValidCatrobatFile()
  {
    $filepath = self::FIXTUREDIR . "test.catrobat";
    assertTrue(file_exists($filepath), "File not found");
    $this->files[] = new UploadedFile($filepath, "test.catrobat");
  }

  /**
   * @Given /^I have a Catrobat file with an bad word in the description$/
   */
  public function iHaveACatrobatFileWithAnRudeWordInTheDescription()
  {
    $filepath = self::FIXTUREDIR . "GeneratedFixtures/program_with_rudeword_in_description.catrobat";
    assertTrue(file_exists($filepath),"File not found");
    $this->files[] = new UploadedFile($filepath,"program_with_rudeword_in_description.catrobat");
  }

  /**
   * @Given /^I have a parameter "([^"]*)" with the md5checksum my file$/
   */
  public function iHaveAParameterWithTheMdchecksumMyFile($parameter)
  {
    $this->request_parameters[$parameter] = md5_file($this->files[0]->getPathname());
  }

  /**
   * @Given /^I have a parameter "([^"]*)" with an invalid md5checksum of my file$/
   */
  public function iHaveAParameterWithAnInvalidMdchecksumOfMyFile($parameter)
  {
    $this->request_parameters[$parameter] = "INVALIDCHECKSUM";
  }

  /**
   * @When /^I upload a catrobat program$/
   */
  public function iUploadACatrobatProgram()
  {
    $this->iHaveAValidCatrobatFile();
    $this->iHaveAParameterWithTheMdchecksumMyFile("fileChecksum");
    $this->request_parameters["username"] = $this->user;
    $this->request_parameters["token"] = "cccccccccc";
    $this->iPostTheseParametersTo("/api/upload/upload.json");
  }

  /**
   * @When /^I upload a catrobat program with the same name$/
   */
  public function iUploadACatrobatProgramWithTheSameName()
  {
    $this->last_response = $this->client->getResponse()->getContent();
    $this->iPostTheseParametersTo("/api/upload/upload.json");
  }

  /**
   * @Then /^it should be updated$/
   */
  public function itShouldBeUpdated()
  {
    $last_json = json_decode($this->last_response, true);
    $json = json_decode($this->client->getResponse()->getContent(), true);
    assertEquals($last_json['projectId'], $json['projectId']);
  }

  /**
   * @Given /^I have a parameter "([^"]*)" with the md5checksum of "([^"]*)"$/
   */
  public function iHaveAParameterWithTheMdchecksumOf($parameter, $file)
  {
    $this->request_parameters[$parameter] = md5_file($this->files[0]->getPathname());
  }

  /**
   * @Given /^the next generated token will be "([^"]*)"$/
   */
  public function theNextGeneratedTokenWillBe($token)
  {
    $token_generator = $this->kernel->getContainer()->get("tokengenerator");
    $token_generator->setTokenGenerator(new FixedTokenGenerator($token));
  }

  /**
   * @Given /^the current time is "([^"]*)"$/
   */
  public function theCurrentTimeIs($time)
  {
    $date = new \DateTime($time, new \DateTimeZone('UTC'));
    $time_service = $this->kernel->getContainer()->get("time");
    $time_service->setTime(new FixedTime($date->getTimestamp()));
  }


  /**
   * @Given /^I have the POST parameters:$/
   */
  public function iHaveThePostParameters(TableNode $table)
  {
    $parameters = $table->getHash();
    foreach($parameters as $parameter)
    {
      $this->request_parameters[$parameter['name']] = $parameter['value'];
    }
  }

  /**
   * @When /^I try to register without (.*)$/
   */
  public function iTryToRegisterWithout($missing_parameter)
  {
    $this->prepareValidRegistrationParameters();
    switch ($missing_parameter)
    {
      case "a country" :
        unset($this->request_parameters['registrationCountry']);
        break;
      case "a password" :
        unset($this->request_parameters['registrationPassword']);
        break;
      default :
        throw new PendingException();
    }
    $this->sendPostRequest("/api/loginOrRegister/loginOrRegister.json");
  }

  /**
   * @Given /^I have otherwise valid registration parameters$/
   */
  public function iHaveOtherwiseValidRegistrationParameters()
  {
    $this->prepareValidRegistrationParameters();
  }

  /**
   * @When /^I try to register$/
   */
  public function iTryToRegister()
  {
    $this->sendPostRequest("/api/loginOrRegister/loginOrRegister.json");
  }

  /**
   * @Given /^there are uploaded programs:$/
   */
  public function thereAreUploadedPrograms(TableNode $table)
  {
    throw new PendingException();
  }

  /**
   * @When /^i download "([^"]*)"$/
   */
  public function iDownload($arg1)
  {
    $this->client = $this->kernel->getContainer()->get('test.client');
    $this->client->request('GET', $arg1);
  }

  /**
   * @Then /^i should receive a file$/
   */
  public function iShouldReceiveAFile()
  {
    $content_type = $this->client->getResponse()->headers->get('Content-Type');
    assertEquals("application/zip", $content_type);
  }

  /**
   * @When /^I register a new user$/
   */
  public function iRegisterANewUser()
  {
    $this->prepareValidRegistrationParameters();
    $this->sendPostRequest("/api/loginOrRegister/loginOrRegister.json");
  }
  
  /**
   * @When /^I try to register another user with the same email adress$/
   */
  public function iTryToRegisterAnotherUserWithTheSameEmailAdress()
  {
    $this->prepareValidRegistrationParameters();
    $this->request_parameters['registrationUsername'] = "AnotherUser";
    $this->sendPostRequest("/api/loginOrRegister/loginOrRegister.json");
  }



    /**
     * @Given /^I am using pocketcode for "([^"]*)" with version "([^"]*)"$/
     */
    public function iAmUsingPocketcodeForWithVersion($platform, $version)
    {
      $this->generateProgramFileWith(array("platform" => $platform, "applicationVersion" => $version));
    }

    /**
     * @When /^I upload a program$/
     */
    public function iUploadAProgram()
    {
      $this->uploadProgramFileAsDefaultUser(sys_get_temp_dir(), "program_generated.catrobat");
    }

    /**
     * @Given /^I am using pocketcode with language version "([^"]*)"$/
     */
    public function iAmUsingPocketcodeWithLanguageVersion($version)
    {
      $this->generateProgramFileWith(array("catrobatLanguageVersion" => $version));
    }

    /**
     * @Then /^The upload should be successful$/
     */
    public function theUploadShouldBeSuccessful()
    {
        $response = $this->client->getResponse();
        $responseArray = json_decode($response->getContent(), true);
        assertEquals(200, $responseArray["statusCode"]);
    }
    
    /**
     * @Given /^the server name is "([^"]*)"$/
     */
    public function theServerNameIs($name)
    {
        $this->hostname = $name;
    }
    
    /**
     * @Given /^I use a secure connection$/
     */
    public function iUseASecureConnection()
    {
        $this->secure = true;
    }
    
}
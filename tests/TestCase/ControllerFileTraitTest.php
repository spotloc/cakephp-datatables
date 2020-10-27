<?php
/*
 * Copyright (c) Spotloc 2020. Tous droits réservés.
 */

namespace App\Test\TestCase;


use Cake\Controller\Component\FlashComponent;
use Cake\Controller\ComponentRegistry;
use Cake\Datasource\ModelAwareTrait;
use Cake\Filesystem\File;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Http\ServerRequest;
use Cake\Utility\Text;
use DataTables\Traits\ControllerFileTrait;


require TESTS.DS. 'mockUpload.php';

/**
 * ControllerFileTrait Test Case
 *
 * @uses \App\Controller\AppController
 */
class ControllerFileTraitTest extends TestCase
{
    use IntegrationTestTrait, ControllerFileTrait, ModelAwareTrait;

    public $fixtures = [];

    /** @var \Cake\Controller\Controller */
    private $controller;

    public function getFilePath($p)
    {
        return 'test';
    }

    public function setUp(): void
    {
        parent::setUp();

        $model = $this->getMockForModel('Cities', ['getFilePath']);

        $model->expects($this->any())
            ->method('getFilePath')
            ->willReturn(
                $this->returnValue('s'),
                $this->returnValue(TESTS . 'assets' . DS . 'test_file.test'),
                $this->returnValue(TESTS . 'assets' . DS . 'test_file.test')
            );


        $model->addBehavior('File', ['path' => TESTS . 'assets' . DS, 'field' => 'file']);
        $request = new ServerRequest();
        $this->response = new Response();

        $this->controller = $this->getMockBuilder('Cake\Controller\Controller')
            ->setConstructorArgs([$request, $this->response])
            ->setMethods(null)
            ->getMock();


        $registry = new ComponentRegistry($this->controller);
        $this->Flash = new FlashComponent($registry);

        $this->modelClass = 'Cities';
        $this->loadModel();

    }

    public function testShowFile()
    {
        $this->assertNull($this->showFile('bad_file'));

        $response = $this->showFile('good');

        $this->assertNotNull($response);
        $this->assertInstanceOf(File::class, $response->getFile());

    }


    public function testLoadFiles()
    {
        $c = new City();
        $c->file = 'file_name';

        $response = $this->loadFiles([$c]);

        $this->assertArrayHasKey($c->file, $response['files'],
            "Le tableau retourné contient la description du fichier.'");
    }

    function is_uploaded_file($name) {
        debug('invoked');
        return ('awesome' === $name);
    }


    public function testSetFile()
    {
        $basePngFile = new File(TESTS . 'assets' . DS ."logo_spotloc.png");
        $fileName = Text::uuid().".png";
        $destPath = sys_get_temp_dir().DS.$fileName;
        $basePngFile->copy($destPath);

        $fileData = [
            "tmp_name" => $destPath,
            'name' => "logo_spotloc.png"
        ];
        $response = $this->setFile($fileData);

        $this->assertArrayHasKey('files', $response, "La réponse contient les attributs du fichier enregistré");
        $this->assertArrayHasKey('upload', $response, "La réponse contient la référencets du fichier enregistré");
        $this->assertEquals(1, count($response['upload']));
    }


    public function tearDown(): void
    {
        parent::tearDown();
        TableRegistry::getTableLocator()->clear();
    }
}
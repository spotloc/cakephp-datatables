<?php
/*
 * Copyright (c) Spotloc 2020. Tous droits réservés.
 */


namespace DataTables\Traits;


use Cake\Controller\Controller;
use Cake\Filesystem\File;
use \Cake\Http\Response;
use Cake\Log\Log;
use Cake\ORM\Entity;
use Cake\Routing\Router;
use Laminas\Diactoros\UploadedFile;


/**
 * Trait ControllerFileTrait
 *
 * @package App\Traits
 *
 *
 * @mixin Controller
 */
trait ControllerFileTrait
{

    /**
     * Affiche le contenu du fichier.
     *
     * @param  string|null  $name  Nom du ficher à afficher
     *
     * @return Response
     */
    public function showFile(string $name = null)
    {
        $file = new File($this->{$this->modelClass}->getFilePath($name));
        if ($file && $file->exists()) {
            return $this->response->withFile($file->path);
        } else {
            $this->Flash->set(__d('data_tables', 'missing_file'));

        }
        return null;
    }

    /**
     * Construit le tableau des fichiers associés aux entities, celle-ci
     * doivent posséder le behavior 'File'
     *
     * @param  Entity[]  $entities
     *
     * @return string[]
     *
     * @see \App\Model\Behavior\FileBehavior
     */
    public function loadFiles(array $entities): array
    {
        $files = ["files"];

        $fields = $this->{$this->modelClass}->getBehavior('File')
            ->getConfig('field');
        foreach ($entities as $e) {
            if (is_object($e)) {
                foreach ($fields as $field) {
                    $fileInfo = $this->{$this->modelClass}->fileInfo($e->{$field});
                    if ($fileInfo) {
                        $files["files"][$fileInfo["filename"]] = $fileInfo;
                        $files["files"][$fileInfo["filename"]]["web_path"]
                            = Router::url([
                            'controller' => $this->getName(),
                            'action' => 'showFile',
                            $fileInfo["filename"],
                        ], true);
                    } else {
                        $files["files"][$e->{$field}] = $e->{$field};
                    }
                }
            }
        }
        return $files;
    }


    /**
     * Enregistre le fichier associé à l'entité.
     *
     * @param  UploadedFile  $data
     *
     * @return array
     */
    public function fileInfo(string $fileName): array
    {
        $response = [];
        $fileInfo = $this->{$this->modelClass}->fileInfo($fileName);

        if ($fileInfo) {
            $response['data'] = [];
            $response['files']['files'][$fileInfo["id"]] = [
                "id" => $fileInfo["id"],
                "filename" => $fileInfo["filename"],
                "type" => $fileInfo["type"],
                "web_path" => Router::url([
                    'controller' => $this->getName(),
                    'action' => 'showFile',
                    $fileInfo["filename"],
                ],
                    true),
            ];
            $response['upload'] = ["id" => $fileInfo["filename"]];
        }
        return $response;
    }

    /**
     * Renvoie une erreur à la datatable
     *
     * @param  \Cake\Datasource\EntityInterface  $entity
     *
     * @return array
     */
    public function setErrorResponse(Entity $entity)
    {
        $errorsArray = [];
        foreach ($entity->getErrors() as $e => $errors) {
            foreach ($errors as $m => $err) {
                if (is_array($err)) {
                    foreach ($err as $k => $v) {
                        $errorsArray["fieldErrors"][] = [
                            "name" => $e . '.' . $m,
                            "status" => $v,
                        ];
                    }
                } else {
                    $errorsArray["fieldErrors"][] = [
                        "name" => $e,
                        "status" => $err,
                    ];
                }
            }
        }
        return $errorsArray;
    }
}
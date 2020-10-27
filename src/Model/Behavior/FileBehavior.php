<?php
/*
 * Copyright (c) Spotloc 2020. Tous droits réservés.
 */

namespace DataTables\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\I18n\Number;
use Cake\Log\Log;
use Cake\ORM\Behavior;
use Cake\ORM\PropertyMarshalInterface;
use Cake\Utility\Text;
use Cake\Validation\Validator;
use finfo;
use Intervention\Image\ImageManager;
use Laminas\Diactoros\Exception\UploadedFileErrorException;
use Laminas\Diactoros\UploadedFile;
use Mimey\MimeTypes;
use SplFileObject;

/**
 * File behavior
 */
class FileBehavior extends Behavior
{

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        "path" => WWW_ROOT,
        "max_size" => 1024 * 100,
        "field" => ["fichier"],
        "when" => null
    ];

    public function initialize(array $config): void
    {
        parent::initialize($config);
        if (!is_array($this->getConfig('field'))) {
            $this->setConfig('field', [$this->getConfig('field')]);
        }
    }

    public function validationUpload(Validator $validator)
    {
        $maxSize = $this->getConfig('max_size');
        foreach ($this->getConfig('field') as $configField) {
            $validator
                ->uploadedFile("{$configField}_tmp", [
                    "maxSize" => $maxSize

                ], __d('data_tables', 'file_behavior_file_max_size', [Number::toReadableSize($maxSize)]),
                    $this->getConfig('when')
                );

            if ($allowedExtensions = $this->getConfig('extensions')) {
                $validator->add("{$configField}_tmp", 'validExtension', [
                        'rule' => ['extension', $allowedExtensions],
                        'message' => __d('data_tables', 'file_behavior_file_extension',
                            [join(', ', $allowedExtensions)])
                ]);
            }
        }
        return $validator;
    }

    /**
     * @param  \Laminas\Diactoros\UploadedFile  $uploadedFile
     *
     * @return array|null
     */
    public function setFile(UploadedFile $uploadedFile)
    {
        $fileName = $this->makeUploadedFileName($uploadedFile);

        if (!$fileName) {
            return null;
        }

        $filePath = $this->getConfig('path') . $fileName;

        try {
            $uploadedFile->moveTo($filePath);

            $exif = @exif_read_data($filePath);
            if ($exif && isset($exif['Orientation'])) {
                $manager = new ImageManager(array('driver' => 'gd'));
                $image = $manager->make($filePath);
                $image = $image->orientate();
                $image->save($filePath);
            }
            return [$fileName, $filePath, $uploadedFile->getSize(), $uploadedFile->getClientMediaType()];
        } catch (UploadedFileErrorException|InvalidArgumentException|UploadedFileErrorException $e) {
            return null;
        }
    }

    /**
     * @param  string  $fileName
     *
     * @return array
     */
    public function fileInfo(?string $fileName): array
    {
        try {
            $filePath = $this->getFilePath($fileName);
            if ($fileName && $file = new SplFileObject($filePath)) {
                $mimes = new MimeTypes();
                $mimeType = $mimes->getMimeType($file->getFileInfo()->getExtension());
                $info = [
                    "id" => $fileName,
                    "filename" => $fileName,
                    "filesize" => $file->getSize(),
                    "type" => $mimeType,
                ];
                $file = null;

                return $info;
            } else {
                return [];
            }
        } catch (\Exception $e) {
            return [];
        }
    }

    public function loadFile($name)
    {
        $file = new SplFileObject($this->getFilePath($name));

        return $file->isFile() ? $file : null;
    }

    public function getFilePath($name)
    {
        return $this->getConfig('path') . $name;
    }

    public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options): void
    {
        foreach ($this->getConfig('field') as $configField) {
            if (!empty($data[$configField]) && $data[$configField] instanceof UploadedFile) {
                $data["{$configField}_tmp"] = $data[$configField];
                $data[$configField] = null;
            }
        }
    }


    public function afterMarshal(EventInterface $event, EntityInterface $entity, \ArrayObject $options): void
    {
        foreach ($this->getConfig('field') as $configField) {
            if ($entity->getError("{$configField}_tmp")) {
                $entity->setError($configField, $entity->getError("{$configField}_tmp"));
                $entity->setError("{$configField}_tmp", null, true);
            } else {
                if (!empty($event->getData('data')["{$configField}_tmp"])) {
                    if ($fileInfo = $this->setFile($event->getData('data')["{$configField}_tmp"])) {
                        $entity->{$configField} = $fileInfo[0];
                    } else {
                        $entity->setError($configField, __d('data_tables', "file_behavior_file_error"));
                    }
                }
            }
        }
    }

    /**
     * @param  \Laminas\Diactoros\UploadedFile  $uploadedFile
     *
     * @return string|null
     */
    public function makeUploadedFileName(UploadedFile $uploadedFile) : ?string
    {
        $mimes = new MimeTypes();
        $fileExtension = $mimes->getExtension($uploadedFile->getClientMediaType());

        if (!$fileExtension) {
            $pathInfo = pathinfo($uploadedFile->getClientFilename());
            if (isset($pathInfo['extension'])) {
                $fileExtension = $pathInfo['extension'];
            } else {
                return null;
            }
        }
        return Text::uuid() . '.' . $fileExtension;
    }
}

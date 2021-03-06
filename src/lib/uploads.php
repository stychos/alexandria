<?php

namespace alexandria\lib;

class uploads
{
    protected $files = [];

    /**
     * uploads constructor.
     *
     * @param string|null $name
     */
    public function __construct(string $name = null)
    {
        foreach ($_FILES as $upname => $upvalue)
        {
            if ($name && $upname !== $name)
            {
                continue;
            }

            if (!is_array($upvalue['name']))
            {
                $this->files [] = $upvalue;
            }
            else
            {
                foreach ($upvalue['name'] as $index => $names)
                {
                    for ($i = 0; $i < count($names); $i++)
                    {
                        $this->files[$upname][$index][] = [
                            'name'     => $_FILES[$upname]['name'][$index][$i],
                            'tmp_name' => $_FILES[$upname]['tmp_name'][$index][$i],
                            'type'     => $_FILES[$upname]['type'][$index][$i],
                            'size'     => $_FILES[$upname]['size'][$index][$i],
                            'error'    => $_FILES[$upname]['error'][$index][$i],
                        ];
                    }
                }
            }
        }
    }

    /**
     * @param string $to
     * @param int    $chmod
     *
     * @return array
     * @throws \Exception
     */
    public function save_all(string $to, int $chmod = 0644)
    {
        $ret = [];
        for ($i = 0; $i < count($this->files); $i++)
        {
            if ($saved = $this->save($i, $to, null, $chmod))
            {
                $ret [] = $saved;
            }
        }

        return $ret;
    }

    /**
     * @param int         $index
     * @param string      $to
     * @param string|null $filename
     * @param int         $chmod
     *
     * @return bool|string
     * @throws \Exception
     */
    public function save(int $index, string $to, string $filename = null, int $chmod = 0644)
    {
        if (!is_dir($to) && !mkdir($to, 0775, true))
        {
            throw new \Exception('Can not use specified destination directory.');
        }

        if (empty($filename))
        {
            $filename = uniqid().preg_replace('/^.*(\.[^\.]+)$/', '\1', $this->files[$index]['name']);
        }

        if (move_uploaded_file($this->files[$index]['tmp_name'], $to.DIRECTORY_SEPARATOR.$filename))
        {
            chmod($to.DIRECTORY_SEPARATOR.$filename, $chmod);
            return $filename;
        }

        return false;
    }
}

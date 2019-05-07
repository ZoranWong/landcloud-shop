<?php
namespace app\filesystem;
interface FileInterface{
    function move($path);
    function getContent();
    function getFileName();
    function setFileName($fileName);
    function getFilePath();
    function setFilePath($path);
    function getMime($mime);
    function getSize();
    function getExt();
    function hash();
}
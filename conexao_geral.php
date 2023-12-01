<?php 
function conexaBD()
{
    // date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $servidor = "35.199.66.5";
    $usuario = "root";
    $senha = "abc123**";
    $dbname = "fvblocadora";

    $conn = mysqli_connect($servidor, $usuario, $senha, $dbname);
    $conn->set_charset("utf8");

    return $conn;
};
?>
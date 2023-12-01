<?php
header('Content-type: text/html; charset=utf-8; application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {

    session_start();
}

require '../Slim/Slim.php';
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

//CONEXÃO GERAL BD
require('../../includes/conexao_geral.php');

//post de Autenticação - WEB - APP
$app->post('/authenticate(/)', function () use ($app) {

    date_default_timezone_set('America/Sao_Paulo');

    $jsonbruto = $app->request()->getBody();
    $jsonObj = json_decode($jsonbruto, true);

    $token = MD5(MD5('quaestum') . $jsonObj['re'] . date("dmy"));

    // conexao DB online
    $conn = conexaBD();

    // Estabeleça o fuso horário
    date_default_timezone_set('America/Sao_Paulo');

    //query de login
    $query = "SELECT * FROM cac_usuarios WHERE re='" . $jsonObj['re'] . "' and senha = md5('" . $jsonObj['senha'] . "');";
    $queryJourney = "SELECT * FROM cac_jornada WHERE re_funcionario = '" . $jsonObj['re'] . "'
    AND  `data` <= now()  - interval 3 hour AND STATUS > '0'  ";
    //Captura o Resultado
    $result = mysqli_query($conn, $query);
    $resultJourney = mysqli_query($conn, $queryJourney);

    $consulta_result = mysqli_fetch_assoc($result);

    //Conta as linhas que retornaram
    $row_cnt = $result->num_rows;

    if ($resultJourney->num_rows > 0) {



        if ($row_cnt > 0) {
            $queryUpdateToken = "UPDATE cac_usuarios   SET token_notificacao = '" . $jsonObj['tokenNotificacao'] . "'  WHERE re='" . $jsonObj['re'] . "' ";
            $result = mysqli_query($conn, $queryUpdateToken);
            //Retorna status
            echo '{
            "autenticacao": "' . $consulta_result['acesso'] . '",
            "token":"' . $token . '",
            "re":"' . $jsonObj['re'] . '",
            "id":"' . $consulta_result['id'] . '",
            "usuario":"' . $consulta_result['usuario'] . '",
            "acesso":"' . $consulta_result['acesso'] . '",
            "companhia":"' . $consulta_result['companhia'] . '"
         
        }';
        } else {
            //Retorna status
            echo '{
            "autenticacao": 0
        }';
            $_SESSION["autenticacao"] = 0;
        }
    } else {
        echo '{
            "autenticacao": 99
        }';
    }
});


$app->post('/retornaChamados(/)', function () use ($app) {

    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $headers =  $app->request()->headers()->all();
    $token =  $headers['Token'];
    // $Acesso =  $headers['Acess'];
    // echo $headers['Token'];
    $conn = conexaBD();
    $comp = '';
    $jsonbruto = $app->request()->getBody();
    $jsonObj = json_decode($jsonbruto, true);


    $Acesso = $jsonObj['acesso'];
    $comp = $jsonObj['companhia'];
    $id = $jsonObj["id"];

    // if ($comp != '' && $Acesso <= 2) {
    //     $query = "SELECT *, cop.desc,op.id as bo FROM `cac_operacao` as op, `cac_companhia`as cop 
    //     WHERE(status <> 6) AND op.companhia ='$comp' and cop.id = op.companhia 
    //     ORDER BY cod_ocorrencia DESC";
    // } else  if ($Acesso == 4) {
    //     $query = "SELECT *, cop.desc, op.id as bo  FROM `cac_operacao` as op, `cac_companhia`as cop
    //      WHERE (status <> 6) AND cop.id = op.companhia ORDER BY cod_ocorrencia DESC";
    // } else  if ($Acesso <= 2) {
    //     $query = "SELECT  *, cop.desc, op.id as bo FROM `cac_operacao` as op, `cac_companhia`as cop
    //      WHERE (status <> 6) AND cop.id = op.companhia ORDER BY cod_ocorrencia DESC";
    // } else if ($comp != '' && $Acesso == 3) {
    //     $query = "SELECT *, cop.desc,op.id as bo FROM `cac_operacao` as op, `cac_companhia`as cop 
    //     WHERE status in( 1 , 4 , 8) and  op.companhia ='$comp'
    //      and cop.id = op.companhia and op.condutor = $id ORDER BY cod_ocorrencia DESC";
    // }

    $query = "SELECT *, cop.desc,op.id as bo FROM `cac_operacao` as op, `cac_companhia`as cop 
    WHERE status in( 1 , 4 , 8) and  op.companhia ='$comp'
     and cop.id = op.companhia and op.condutor = $id ORDER BY cod_ocorrencia DESC";

    $result = mysqli_query($conn, $query);
    $row_cnt = $result->num_rows;


    $arr = array();
    $i = 0;
    $sub = '';

    if ($token) {
        if ($row_cnt > 0) {
            while ($consulta_result = mysqli_fetch_assoc($result)) {
                $arr[$i]["id"] =  $consulta_result['bo'];
                $arr[$i]["cod_ocorrencia"] =  $consulta_result['cod_ocorrencia'];
                $arr[$i]["tipo_operacao"] =  $consulta_result['tipo_operacao'];
                $arr[$i]["nome_contatante"] =  $consulta_result['nome_contatante'];
                $arr[$i]["rua"] =  $consulta_result['rua'];
                $arr[$i]["placa_veic"] =  $consulta_result['placa_veic'];
                $arr[$i]["condutor"] =  $consulta_result['condutor'];
                $arr[$i]["numero"] =  $consulta_result['numero'];
                $arr[$i]["companhia"] = $consulta_result['desc'];
                $arr[$i]["registro_hora_data"] = date('d/m/Y H:i', strtotime($consulta_result['registro_hora_data']));
                $arr[$i]["prioridade"] = $consulta_result['prioridade'];
                if ($consulta_result['sub-companhia'] != '') {
                    $query = "SELECT * FROM `cac_sub_companhia` WHERE id = " . $consulta_result['sub-companhia'];
                    $rs = mysqli_query($conn, $query);
                    $cr = mysqli_fetch_assoc($rs);
                    $sub = $cr['desc'];
                }
                $arr[$i]["sub_companhia"] = $sub;
                $arr[$i]["status"] = $consulta_result['status'];

                $i++;
            }
            echo json_encode($arr);
        } else {
            echo 0;
        }
    }
});


$app->get('/infoChamado/:id', function ($id) use ($app) {
    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online

    $conn = conexaBD();

    $query = "SELECT *, cop.desc, op.id as bo  FROM `cac_operacao` as op, `cac_companhia`as cop 
    WHERE cop.id = op.companhia AND op.id = " . $id;
    $result = mysqli_query($conn, $query);
    $row_cnt = $result->num_rows;
    $consulta_result = mysqli_fetch_assoc($result);

    $headers =  $app->request()->headers()->all();
    $token =  $headers['Token'];

    $arr = array();
    $i = 0;
    $cond = '';
    $sub = '';
    if ($token) {
        if ($row_cnt > 0) {
            $arr[$i]["id"] =  $consulta_result['bo'];
            $arr[$i]["nome_contatante"] =  $consulta_result['nome_contatante'];
            $arr[$i]["sexo"] =  $consulta_result['sexo'];
            $arr[$i]["cod_ocorrencia"] =  $consulta_result['cod_ocorrencia'];
            $arr[$i]["cpf_contatante"] = $consulta_result['cpf_contatante'];
            $arr[$i]["rg_contatante"] = $consulta_result['rg_contatante'];
            $arr[$i]["tipo_operacao"] = $consulta_result['tipo_operacao'];
            $arr[$i]["orgao"] = $consulta_result['orgao'];
            $arr[$i]["companhia"] = $consulta_result['desc'];
            $arr[$i]["rua"] = $consulta_result['rua'];
            $arr[$i]["numero"] = $consulta_result['numero'];
            $arr[$i]["complemento"] = $consulta_result['complemento'];
            $arr[$i]["cep"] = $consulta_result['cep'];
            $arr[$i]["cidade"] = $consulta_result['cidade'];
            $arr[$i]["bairro"] = $consulta_result['bairro'];
            $arr[$i]["bo_gcm"] = $consulta_result['bo_gcm'];

            $arr[$i]["resp_abertura"] = $consulta_result['resp_abertura'];
            $arr[$i]["resp_encaminhamento"] = $consulta_result['resp_encaminhamento'];
            $arr[$i]["resp_aceitar"] = $consulta_result['resp_aceitar'];
            $arr[$i]["resp_sair_base"] = $consulta_result['resp_sair_base'];
            $arr[$i]["resp_informacoes"] = $consulta_result['resp_informacoes'];
            $arr[$i]["resp_encerramento"] = $consulta_result['resp_encerramento'];

            $arr[$i]["registro_hora_data"] = retornDateFormated($consulta_result['registro_hora_data']);
            $arr[$i]["data_hora_encaminhamento"] = retornDateFormated($consulta_result['data_hora_encaminhamento']);
            $arr[$i]["data_hora_aceitar"] = retornDateFormated($consulta_result['data_hora_aceitar']);
            $arr[$i]["data_hora_sair_base"] = retornDateFormated($consulta_result['data_hora_sair_base']);
            $arr[$i]["data_hora_informacoes"] = retornDateFormated($consulta_result['data_hora_informacoes']);
            $arr[$i]["data_hora_encerramento"] = retornDateFormated($consulta_result['data_hora_encerramento']);



            if ($consulta_result['condutor'] <> '' || $consulta_result['condutor'] != '') {
                $query = "SELECT * FROM `cac_usuarios` WHERE id = " . $consulta_result['condutor'];
                $rs = mysqli_query($conn, $query);
                $cr = mysqli_fetch_assoc($rs);
                $cond = $cr['usuario'];
            }
            $arr[$i]["condutor"] = $cond;
            if ($consulta_result['sub-companhia'] <> '' || $consulta_result['sub-companhia'] != '') {
                $query = "SELECT * FROM `cac_sub_companhia` WHERE id = " . $consulta_result['sub-companhia'];
                $rs = mysqli_query($conn, $query);
                $cr = mysqli_fetch_assoc($rs);
                $sub = $cr['desc'];
            }
            $arr[$i]["sub_companhia"] = $sub;
            $arr[$i]["placa_veic"] = $consulta_result['placa_veic'];
            $arr[$i]["prioridade"] = $consulta_result['prioridade'];
            $arr[$i]["data"] = $consulta_result['data'];
            $arr[$i]["desc_atendimento"] = $consulta_result['desc_atendimento'];
            $arr[$i]["desc_operacao"] = $consulta_result['desc_operacao'];
            $arr[$i]["status"] = $consulta_result['status'];
            $arr[$i]["lat_aceite"] = $consulta_result['lat_aceite'];
            $arr[$i]["long_aceite"] = $consulta_result['long_aceite'];
            $arr[$i]["telefone_contatante"] = $consulta_result['telefone_contatante'];
            $arr[$i]["email_contatante"] = $consulta_result['email_contatante'];
            $arr[$i]["foto1"] = $consulta_result['foto1'];
            $arr[$i]["foto2"] = $consulta_result['foto2'];
            $arr[$i]["foto3"] = $consulta_result['foto3'];
            $arr[$i]["foto4"] = $consulta_result['foto4'];
            $arr[$i]["foto5"] = $consulta_result['foto5'];


            echo json_encode($arr[0]);
        } else {
            echo 0;
        }
    }
});

$app->post('/alterarChamado(/)', function () use ($app) {
    date_default_timezone_set('America/Sao_Paulo');



    $jsonbruto = $app->request()->getBody();
    $jsonObj = json_decode($jsonbruto, true);

    // conexao DB online

    $conn = conexaBD();

    $headers =  $app->request()->headers()->all();
    $token =  $headers['Token'];
    if ($token) {
        $idop = $jsonObj['id'];
        $nome_cont = $jsonObj['nome_contatante'];
        $prioridade = $jsonObj['prioridade'];

        $cpf = $jsonObj['cpf_contatante'];
        $rg = $jsonObj['rg_contatante'];
        $sexo = $jsonObj['sexo'];
        $email = $jsonObj['email_contatante'];
        $cel = $jsonObj['telefone_contatante'];
        $end = $jsonObj['rua'];
        $cidade = $jsonObj['cidade'];
        $cep = $jsonObj['cep'];
        $situationOperation = $jsonObj['situationOperation'];

        $bairro = $jsonObj['bairro'];
        $numero = $jsonObj['numero'];
        $obs = $jsonObj['desc_atendimento'];
        $obsLocal = $jsonObj['desc_operacao'];
        $fotos1 = $jsonObj['foto1'];
        $fotos2 = $jsonObj['foto2'];
        $fotos3 = $jsonObj['foto3'];
        $fotos4 = $jsonObj['foto4'];
        $fotos5 = $jsonObj['foto5'];






        //Query de Update
        $query = "UPDATE `cac_operacao` SET `nome_contatante` = '$nome_cont', 
         `prioridade` = '$prioridade', `cpf_contatante` = '$cpf',
         `rg_contatante` = '$rg', `email_contatante`='$email', `telefone_contatante`='$cel', 
         `rua`='$end', `numero`='$numero',  `cep`='$cep',  `cidade`='$cidade', 
         `bairro`='$bairro', `desc_operacao`='$obs', `desc_atendimento`='$obsLocal', 
         `data_hora_informacoes` = NOW() - INTERVAL 3 hour ,
         sexo = '$sexo', `status` = '$situationOperation',
         foto1 = '$fotos1',foto2 = '$fotos2',foto3 = '$fotos3',foto4 = '$fotos4',foto5 = '$fotos5'
         WHERE `id` = '$idop'";
        //Executa a Query
        mysqli_query($conn, $query);

        echo $conn->error;
    }
});


$app->post('/alterarOcorrencia(/)', function () use ($app) {
    date_default_timezone_set('America/Sao_Paulo');


    $jsonbruto = $app->request()->getBody();
    $jsonObj = json_decode($jsonbruto, true);


    // conexao DB online

    $conn = conexaBD();
    $headers =  $app->request()->headers()->all();
    $token_padrao =  $headers['Token'];

    if ($token_padrao) {
        $idop = $jsonObj['id'];
        $nome_cont = $jsonObj['nome_contatante'];
        $prioridade = $jsonObj['prioridade'];

        $cpf = $jsonObj['cpf_contatante'];
        $rg = $jsonObj['rg_contatante'];
        $sexo = $jsonObj['sexo'];
        $email = $jsonObj['email_contatante'];
        $cel = $jsonObj['telefone_contatante'];
        $end = $jsonObj['rua'];
        $cidade = $jsonObj['cidade'];
        $cep = $jsonObj['cep'];

        $bairro = $jsonObj['bairro'];
        $numero = $jsonObj['numero'];
        $obs = $jsonObj['desc_atendimento'];
        $obsLocal = $jsonObj['desc_operacao'];
        $fotos1 = $jsonObj['foto1'];
        $fotos2 = $jsonObj['foto2'];
        $fotos3 = $jsonObj['foto3'];
        $fotos4 = $jsonObj['foto4'];
        $fotos5 = $jsonObj['foto5'];


        //Query de Update
        $query = "UPDATE `cac_operacao` SET `nome_contatante` = '$nome_cont', 
         `prioridade` = '$prioridade', `cpf_contatante` = '$cpf',
         `rg_contatante` = '$rg', `email_contatante`='$email', `telefone_contatante`='$cel', 
         `rua`='$end', `numero`='$numero',  `cep`='$cep',  `cidade`='$cidade', 
         `bairro`='$bairro', `desc_operacao`='$obs', `desc_atendimento`='$obsLocal', 
         `data_hora_informacoes` = NOW() - INTERVAL 3 hour ,
         sexo = '$sexo',
         foto1 = '$fotos1',foto2 = '$fotos2',foto3 = '$fotos3',foto4 = '$fotos4',foto5 = '$fotos5'
         WHERE `id` = '$idop'";
        //Executa a Query
        mysqli_query($conn, $query);

        echo $conn->error;
        // echo $query;
    }
});



$app->post('/acceptCall(/)', function () use ($app) {
    date_default_timezone_set('America/Sao_Paulo');



    $jsonbruto = $app->request()->getBody();
    $jsonObj = json_decode($jsonbruto, true);

    // conexao DB online

    $conn = conexaBD();

    $headers =  $app->request()->headers()->all();
    $token =  $headers['Token'];
    if ($token) {
        $lat_aceptCall = $jsonObj['lat'];
        $long_aceptCall = $jsonObj['long'];
        $status = $jsonObj['statusCall'];
        $id = $jsonObj['idCall'];

        // Query de Update
        $query = "UPDATE `cac_operacao` SET `lat_aceite` = '$lat_aceptCall',long_aceite='$long_aceptCall',
    `status` = $status ,data_hora_aceitar =NOW() - INTERVAL 3 hour    WHERE `id` = '$id'";
        //Executa a Query
        mysqli_query($conn, $query);
    }
});


$app->post('/allInfos(/)', function () use ($app) {

    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $conn = conexaBD();

    $jsonbruto = $app->request()->getBody();
    $jsonObj = json_decode($jsonbruto, true);

    $headers =  $app->request()->headers()->all();
    $token_padrao =  $headers['Token'];

    $comp = $jsonObj['companhia'];
    $query = "SELECT * FROM `cac_veiculos` as cv where companhia ='$comp'";

    //Captura o Resultado
    $result = mysqli_query($conn, $query);

    //Conta as linhas que retornaram
    $row_cnt = $result->num_rows;

    $arr = array();
    $i = 0;

    if ($token_padrao == 'requyestAllInfos') {
        if ($row_cnt > 0) {
            while ($consulta_result = mysqli_fetch_assoc($result)) {
                $arr[$i]["title"] =  $consulta_result['placa'];

                $arr[$i]["id"] =  $consulta_result['id'];

                $i++;
            }
        }


        if ($comp != '') {
            $query2 = "SELECT p.id, p.numero, p.nome, f.nome as agente, p.tipo_arma, p.descricao
         FROM cac_patrimonio as p, cac_funcionarios as f WHERE p.companhia = '$comp' 
         and p.idAgente = f.id";
        } else {
            $query2 = "SELECT p.id, p.numero, p.nome, c.desc, f.nome as agente, p.tipo_arma, p.descricao 
        FROM cac_patrimonio as p, cac_companhia as c, cac_funcionarios as f WHERE p.companhia = c.id 
        and p.idAgente = f.id";
        }

        $result2 = mysqli_query($conn, $query2);
        $row_cnt2 = $result2->num_rows;

        $arr2 = array();
        $i2 = 0;

        if ($row_cnt2 > 0) {
            while ($consulta_result = mysqli_fetch_assoc($result2)) {
                $desc = '';
                if (isset($consulta_result['desc'])) {
                    $desc = $consulta_result['desc'];
                }
                $arr2[$i2]["id"] = $consulta_result['id'];
                $arr2[$i2]["title"] = $consulta_result['numero'];
                $arr2[$i2]["nome"] = $consulta_result['nome'];
                $arr2[$i2]["desc"] = $desc;
                $arr2[$i2]["agente"] = $consulta_result['agente'];
                $arr2[$i2]["tipo_arma"] = $consulta_result['tipo_arma'];
                $arr2[$i2]["descricao"] = $consulta_result['descricao'];
                $i++;
            }
        }

        if ($comp != '') {
            $companhia = $comp;
            $query = "SELECT id, nome, companhia, matricula FROM cac_funcionarios WHERE companhia = $companhia";
        } else {
            $query = "SELECT id, nome, companhia, matricula FROM cac_funcionarios";
        }

        $result3 = mysqli_query($conn, $query);
        $row_cnt3 = $result3->num_rows;

        $arr3 = array();
        $i3 = 0;

        if ($row_cnt3 > 0) {
            while ($consulta_result = mysqli_fetch_assoc($result3)) {
                $arr3[$i3]["id"] =  $consulta_result['id'];
                $arr3[$i3]["title"] = '' . $consulta_result['matricula'] . '  ' . $consulta_result['nome'] . '';
                $arr3[$i3]["matricula"] =  $consulta_result['matricula'];
                $arr3[$i3]["comp"] =  $consulta_result['companhia'];
                $i3++;
            }
        }

        $dataJson = '{
            "Placas":' . json_encode($arr) . ',   
            "Patrimonio":' . json_encode($arr2) . ' ,
            "Agentes":' . json_encode($arr3) . ' 
           }';
        echo $dataJson;
    }
});

$app->post('/retornaEncerrados(/)', function () use ($app) {
    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $conn = conexaBD();
    $jsonbruto = $app->request()->getBody();
    $jsonObj = json_decode($jsonbruto, true);

    $acesso = $jsonObj['acesso'];
    $comp = $jsonObj['companhia'];
    $id = $jsonObj["id"];
    $query = '';


    $query = "SELECT * FROM cac_operacao WHERE status = '6' AND companhia = $comp  and condutor = $id ";

    $result = mysqli_query($conn, $query);
    $row_cnt = $result->num_rows;

    $headers =  $app->request()->headers()->all();

    $token_padrao =  $headers['Token'];

    $arr = array();
    $i = 0;

    if ($token_padrao) {
        if ($row_cnt > 0) {
            while ($consulta_result = mysqli_fetch_assoc($result)) {

                $arr[$i]["id"] = $consulta_result['id'];
                $arr[$i]["cod_ocorrencia"] = $consulta_result['cod_ocorrencia'];
                $arr[$i]["data"] = retornDateFormated($consulta_result['data']);
                $arr[$i]["rua"] = $consulta_result['rua'];
                $arr[$i]["nome_contatante"] = $consulta_result['nome_contatante'];
                $arr[$i]["tipo_operacao"] = $consulta_result['tipo_operacao'];
                $arr[$i]["desc_operacao"] = $consulta_result['desc_operacao'];
                $i++;
            }
            echo json_encode($arr);
        } else {
            echo 0;
        }
    }
});


$app->get('/retornaChecklist/:acesso/:comp', function ($acesso, $comp) use ($app) {
    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $conn = conexaBD();



    $query = "SELECT c.id, c.tipo_checklist, v.prefixo, v.placa,
         DATE_FORMAT(c.data,'%d/%m/%Y') as data FROM cac_checklist as c, cac_veiculos as v 
         WHERE v.companhia = '$comp' and c.id_veiculo = v.id order by c.data desc";
    $result = mysqli_query($conn, $query);


    $row_cnt = $result->num_rows;
    $headers =  $app->request()->headers()->all();

    $token_padrao =  $headers['Token'];

    $arr = array();
    $i = 0;

    if ($token_padrao) {
        if ($row_cnt > 0) {
            while ($consulta_result = mysqli_fetch_assoc($result)) {
                $desc = '';
                if (isset($consulta_result['desc'])) {
                    $desc = $consulta_result['desc'];
                }
                $arr[$i]["id"] = $consulta_result['id'];
                $arr[$i]["tipo"] = $consulta_result['tipo_checklist'];
                $arr[$i]["prefixo"] = $consulta_result['prefixo'];
                $arr[$i]["placa"] = $consulta_result['placa'];
                $arr[$i]["data"] = $consulta_result['data'];
                // $arr[$i]["desc"] = $desc;
                // $arr[$i]["agente"] = $consulta_result['agente'];
                $i++;
            }
            echo json_encode($arr);
        } else {
            echo 0;
        }
    }
});

$app->post('/saveChecklist(/)', function () use ($app) {
    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $conn = conexaBD();

    $jsonbruto = $app->request()->getBody();
    $jsonObj = json_decode($jsonbruto, true);

    $headers =  $app->request()->headers()->all();

    $token_padrao =  $headers['Token'];



    if ($token_padrao) {
        $queryInsert = "INSERT INTO cac_checklist (id_veiculo , encarregado , 
        auxiliar1 ,  auxiliar2 ,auxiliar3 , `data`,tipo_checklist,fotoDd , fotoDe , fotoTe , 
        fotoTd ,fotoInterna , fotoKm ,patrim_arma ,patrim_outros ,pneuDe ,  pneuDd , 
        pneuTd , pneuTe )
        VALUES('" . $jsonObj['id_veiculo'] . "','" . $jsonObj['encarregado'] . "',
        '" . $jsonObj['firstAgente'] . "','" . $jsonObj['secondAgente'] . "',
        '" . $jsonObj['thirdagente'] . "',NOW() - INTERVAL 3 hour,'" . $jsonObj['tipo'] . "',
        '" . $jsonObj['diantDir'] . "','" . $jsonObj['diantEsq'] . "','" . $jsonObj['trasEsq'] . "',
        '" . $jsonObj['trasDir'] . "','" . $jsonObj['interna'] . "','" . $jsonObj['KM'] . "',
        '" . $jsonObj['patrim_arma'] . "','" . $jsonObj['patrim_outros'] . "',
        '" . $jsonObj['FotoPneuDiantEsq'] . "',
        '" . $jsonObj['FotoPneuDiantDir'] . "','" . $jsonObj['FotoPneuTrastDir'] . "',
        '" . $jsonObj['FotoPneuTrastEsq'] . "' )";
        $result = mysqli_query($conn, $queryInsert);
    }
});


$app->get('/retornaRonda/:re', function ($re) use ($app) {
    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $conn = conexaBD();

    //$id = $_SESSION["id"];

    //SELECIONA NA TABELA SÓ COM RE IGUAL AO RE FUNCIONARIO
    $query = "SELECT * FROM cac_ronda WHERE status >=0 AND 
    re_funcionario = '" . $re . "' ORDER BY id DESC";
    $result = mysqli_query($conn, $query);

    $row_cnt = $result->num_rows;
    $headers =  $app->request()->headers()->all();

    $token_padrao =  $headers['Token'];

    $arr = array();
    $i = 0;

    if ($token_padrao) {
        if ($row_cnt > 0) {
            while ($consulta_result = mysqli_fetch_assoc($result)) {
                $arr[$i]["id"] = $consulta_result['id'];
                $arr[$i]["status"] = $consulta_result['status'];
                $arr[$i]["data"] = retornDateFormated($consulta_result['data']);
                $arr[$i]["re_funcionario"] = $consulta_result['re_funcionario'];
                $arr[$i]["nome_funcionario"] = $consulta_result['nome_funcionario'];
                $arr[$i]["inicio_ronda"] = retornDateFormated($consulta_result['inicio_ronda']);
                $arr[$i]["fim_ronda"] = retornDateFormated($consulta_result['fim_ronda']);

                $i++;
            }
            echo json_encode($arr);
        } else {
            echo 0;
        }
    }
});

$app->put('/CriarRonda(/)', function () use ($app) {
    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $conn = conexaBD();
    $jsonbruto = $app->request()->getBody();
    $jsonObj = json_decode($jsonbruto, true);

    $nome_funcionario = $jsonObj['usuario'];
    $re_funcionario = $jsonObj['re'];
    $status = 1;


    //Query de Cadastro de Ronda
    $query = "INSERT INTO `cac_ronda`(`nome_funcionario`,`re_funcionario`,`status`, `data`) 
    VALUES ('$nome_funcionario', '$re_funcionario','$status', NOW() - INTERVAL 3 hour)";
    mysqli_query($conn, $query);
    //echo $query;  

});
$app->put('/editarRonda(/)', function () use ($app) {
    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $conn = conexaBD();

    $jsonbruto = $app->request()->getBody();
    $jsonObj = json_decode($jsonbruto, true);

    $id = $jsonObj['id'];
    $status = $jsonObj['status'];
    $lat = $jsonObj['lat'];
    $long = $jsonObj['long'];

    $coords = $status == '2' ? ",lat_inicio_ronda = '$lat',long_inicio_ronda=' $long'" : ($status == '0' ? ",lat_fim_ronda = '$lat',long_fim_ronda=' $long'" : "");

    $jornada = $status == '2' ? ",inicio_ronda = NOW() - INTERVAL 3 hour" : ($status == '0' ? ",fim_ronda = NOW() - INTERVAL 3 hour" : "");

    //Alterando o status para STATUS = 0 Fim de ronda
    $query = "UPDATE cac_ronda set status = '$status'
     $jornada $coords  where id = '$id'";
    mysqli_query($conn, $query);
    // echo $query;  

});

$app->get('/seeRound/:id', function ($id) use ($app) {
    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $conn = conexaBD();

    $arr = array();
    $i = 0;
    $query = "SELECT * FROM cac_ronda WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    $linha_result = mysqli_fetch_assoc($result);
    $arr[$i]["id"] = $linha_result['id'];
    $arr[$i]["status"] = $linha_result['status'];
    $arr[$i]["data"] = retornDateFormated($linha_result['data']);
    $arr[$i]["re_funcionario"] = $linha_result['re_funcionario'];
    $arr[$i]["nome_funcionario"] = strtoupper($linha_result['nome_funcionario']);
    $arr[$i]["inicio_ronda"] = retornDateFormated($linha_result['inicio_ronda']);
    $arr[$i]["fim_ronda"] = retornDateFormated($linha_result['fim_ronda']);


    echo json_encode($arr[0]);
    //echo $query;
});


$app->get('/retornaJornada/:re', function ($re) use ($app) {
    date_default_timezone_set('America/Sao_Paulo');


    $conn = conexaBD();


    $query = "SELECT * FROM cac_jornada WHERE status >= 1 AND re_funcionario = '" . $re . "'";

    $result = mysqli_query($conn, $query);
    $row_cnt = $result->num_rows;

    $headers =  $app->request()->headers()->all();

    $token_padrao =  $headers['Token'];

    $arr = array();
    $i = 0;


    if ($token_padrao) {
        if ($row_cnt > 0) {
            while ($consulta_result = mysqli_fetch_assoc($result)) {
                $arr[$i]["id"] = $consulta_result['id'];
                $arr[$i]["status"] = $consulta_result['status'];
                $arr[$i]["data"] = retornDateFormated($consulta_result['data']);
                $arr[$i]["re_funcionario"] = $consulta_result['re_funcionario'];
                $arr[$i]["nome_funcionario"] = $consulta_result['nome_funcionario'];
                $arr[$i]["inicio_jornada"] = retornDateFormated($consulta_result['inicio_jornada']);
                $arr[$i]["inicio_almoco"] = retornDateFormated($consulta_result['inicio_almoco']);
                $arr[$i]["fim_almoco"] = retornDateFormated($consulta_result['fim_almoco']);
                $arr[$i]["fim_jornada"] = retornDateFormated($consulta_result['fim_jornada']);
                $arr[$i]["lat_inicio_jornada"] = $consulta_result['lat_inicio_jornada'];
                $arr[$i]["long_inicio_jornada"] = $consulta_result['long_inicio_jornada'];
                $arr[$i]["lat_inicio_almoco"] = $consulta_result['lat_inicio_almoco'];
                $arr[$i]["long_inicio_almoco"] = $consulta_result['long_inicio_almoco'];
                $arr[$i]["lat_fim_almoco"] = $consulta_result['lat_fim_almoco'];
                $arr[$i]["long_fim_almoco"] = $consulta_result['long_fim_almoco'];
                $arr[$i]["long_fim_almoco"] = $consulta_result['long_fim_almoco'];
                $arr[$i]["long_fim_jornada"] = $consulta_result['long_fim_jornada'];
                $i++;
            }
            echo json_encode($arr);
        } else {
            echo 0;
        }
    }
});
$app->get('/verJornada/:id', function ($id) use ($app) {
    date_default_timezone_set('America/Sao_Paulo');

    $conn = conexaBD();

    $query = "SELECT * FROM cac_jornada WHERE  id = '" . $id . "'";

    $result = mysqli_query($conn, $query);
    $row_cnt = $result->num_rows;

    $headers =  $app->request()->headers()->all();

    $token_padrao =  $headers['Token'];

    $arr = array();
    $i = 0;
    $sub = '';
    if ($token_padrao) {
        if ($row_cnt > 0) {
            $consulta_result = mysqli_fetch_assoc($result);
            $arr[$i]["id"] = $consulta_result['id'];
            $arr[$i]["status"] = $consulta_result['status'];

            $arr[$i]["data"] = retornDateFormated($consulta_result['data']);
            $arr[$i]["re_funcionario"] = $consulta_result['re_funcionario'];
            $arr[$i]["nome_funcionario"] = $consulta_result['nome_funcionario'];
            $arr[$i]["inicio_jornada"] = retornDateFormated($consulta_result['inicio_jornada']);
            $arr[$i]["inicio_almoco"] = retornDateFormated($consulta_result['inicio_almoco']);
            $arr[$i]["fim_almoco"] = retornDateFormated($consulta_result['fim_almoco']);
            $arr[$i]["fim_jornada"] = retornDateFormated($consulta_result['fim_jornada']);



            echo json_encode($arr[0]);
        } else {
            echo 0;
        }
    }
});

$app->put('/editarJornada(/)', function () use ($app) {
    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $conn = conexaBD();

    $jsonbruto = $app->request()->getBody();
    $jsonObj = json_decode($jsonbruto, true);

    $id = $jsonObj['id'];
    $lat = $jsonObj['lat'];
    $long = $jsonObj['long'];
    $status = $jsonObj['status'];

    $values = jorneyStatus($status, $lat, $long);

    //Alterando o status para STATUS = 1 Inicio de jornada
    $query = "UPDATE cac_jornada set `status` = '$status' $values where id = '$id'";
    mysqli_query($conn, $query);
    // echo $id;
    // echo $query;
});

$app->get('/retornaChecklistVisualizar/:id', function ($id) use ($app) {
    date_default_timezone_set('America/Sao_Paulo');

    // conexao DB online
    $conn = conexaBD();


    $query = "SELECT c.id, c.tipo_checklist, v.prefixo, v.placa, 
      c.encarregado, c.auxiliar1, c.auxiliar2, c.auxiliar3,
        DATE_FORMAT(c.data,'%d/%m/%Y') as data, fotoDe, fotoDd, fotoTd, fotoTe, fotoInterna, 
        fotoKm, pneuDd, pneuDe, pneuTe, pneuTd, patrim_arma, patrim_outros FROM cac_checklist as c, 
        cac_veiculos as v WHERE  c.id_veiculo = v.id and c.id = $id";
    $result = mysqli_query($conn, $query);


    $row_cnt = $result->num_rows;

    $headers =  $app->request()->headers()->all();

    $token_padrao =  $headers['Token'];

    $arr = array();
    $i = 0;

    if ($token_padrao) {
        if ($row_cnt > 0) {

            while ($consulta_result = mysqli_fetch_assoc($result)) {
                $arr[$i]["id"] = $consulta_result['id'];
                $arr[$i]["placa"] = $consulta_result['placa'];
                $arr[$i]["tipo"] = $consulta_result['tipo_checklist'];
                $arr[$i]["encarregado"] = $consulta_result['encarregado'];
                $arr[$i]["firstAgente"] = $consulta_result['auxiliar1'];
                $arr[$i]["secondAgente"] = $consulta_result['auxiliar2'];
                $arr[$i]["thirdagente"] = $consulta_result['auxiliar3'];
                $arr[$i]["fotoDd"] = $consulta_result['fotoDd'];
                $arr[$i]["fotoDe"] = $consulta_result['fotoDe'];
                $arr[$i]["fotoTd"] = $consulta_result['fotoTd'];
                $arr[$i]["fotoTe"] = $consulta_result['fotoTe'];
                $arr[$i]["fotoInterna"] = $consulta_result['fotoInterna'];
                $arr[$i]["fotoKm"] = $consulta_result['fotoKm'];
                $arr[$i]["FotopneuDd"] = $consulta_result['pneuDd'];
                $arr[$i]["FotopneuDe"] = $consulta_result['pneuDe'];
                $arr[$i]["FotopneuTe"] = $consulta_result['pneuTe'];
                $arr[$i]["FotopneuTd"] = $consulta_result['pneuTd'];
                $arr[$i]["patrim_arma"] = $consulta_result['patrim_arma'];
                $arr[$i]["patrim_outros"] = $consulta_result['patrim_outros'];
                $arr[$i]["prefixo"] = $consulta_result['prefixo'];
                $arr[$i]["data"] = $consulta_result['data'];

                $i++;
            }
            echo json_encode($arr[0]);
        } else {
            echo 0;
        }
    }
});

function jorneyStatus($type, $lat, $long)
{
    if ($type == 2 || $type == "2") {
        return ",inicio_jornada= NOW() - INTERVAL 3 hour,lat_inicio_jornada='$lat',long_inicio_jornada='$long'";
    } else  if ($type == 3 || $type == "3") {
        return ",inicio_almoco= NOW() - INTERVAL 3 hour,lat_inicio_almoco='$lat',long_inicio_almoco='$long'";
    } else  if ($type == 4 || $type == "4") {
        return ",fim_almoco= NOW() - INTERVAL 3 hour,lat_fim_almoco='$lat',long_fim_almoco='$long'";
    } else  if ($type == 0 || $type == "0") {
        return ",fim_jornada= NOW() - INTERVAL 3 hour,lat_fim_jornada='$lat',long_fim_jornada='$long'";
    }
}


function retornDateFormated($data)
{
    if ($data == null || $data == '') {
        return '';
    } else {
        return  date('d/m/Y H:i:s', strtotime($data));
    }
}

$app->run();

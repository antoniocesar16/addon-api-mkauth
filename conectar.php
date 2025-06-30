<?php 

// pasta: /opt/mk-auth/addon-api-mkauth/conectar.php
require_once '/opt/mk-auth/includes/conexao.php';

// definidos

$conexao = mysqli_connect(CONHOSTNAME, CONUSERNAME, CONPASSWRD, CONDATABASE) or die('Erro ao conectar ao banco de dados: ' . mysqli_connect_error()); 
mysqli_set_charset('utf8')
?>
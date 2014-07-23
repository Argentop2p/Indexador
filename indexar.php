<?
/*
-----------------------------------------------------
Modulo: Script Indexador
Descripción: Indexar los posts, con TAGs.
Entrada: f= forum number; t= que buscar (1:(COM) - 2: (PED));
         l= (letra)  [l es un parámetro opcional]; a= lista por año [a es un parámetro opcional]
         ie: indexar.php?f=5&t=1&l=d&a
         ie: $ php indexar.php --f=5 --t=1 --l=x -a

Autor: snoop852@gmail.com ( para argentop2p.net -ex argentop2p.com.ar-)
Fecha: 08/12/05 -
* Modificado completamente por Predicador (25.01.2006)
* Agregados por elrosti, DAX y Camello_AR

19.02.09 - Agregado de filtro de nuevos y muestra de feedback
20.02.09 - Agregado que ponga como nuevos los posts modificados.
           No indexa los posts cerrados.
26.02.09 - Distingue entre post nuevos y modificados
           Mínima optimización
28.02.09 - Modificados nombres de campos por migración a SMF 2.0 RC1
02.03.09 - Soporte para UTF8
04-10-10 - Agregada compatibilidad con PHP 5.3 (DAX)
03-08-12 - Agregado control de tiempo de ejecución y año de material (Camello_AR)
27-11-13 - Agregado imágenes de HD (Camello_AR)
13-05-14 - Limpieza y estandarización de código
           Parametro "a" para generar lista con año
           Opción de ejecutar por consola
           Arreglo en procesamiento de "l"

-----------------------------------------------------
Por favor, no cambiar los copyleft correspondientes.
Bajo Licencia GPL v2
http://www.gnu.org/licenses/gpl.txt
-----------------------------------------------------
*/

$seg1 = microtime(true);
$version = "1.4a final.";

// Nombre del archivo de salida
// Se complementa con el numero del foro y luego .html
// Ejemplo: para foro 5 y letra a (indexa_html.php?f5&t=1&l=a)  sera: listado_a5.html
$archivo = "listado";

// Nombre del archivo de salida de novedades únicamente, en formato txt
// Dejar vacío para no generar
//$irctxt = "../novedades";
$irctxt = "";
$ircnov = "";

// Por cuantos días algo se considera nuevo.
$diasNuevo = "7";

// Variables de la DB
// $db_server,  $db_user,  $db_passwd, $db_name
require_once('../Settings.php');

// Función que elimina ciertas palabras de una cadena (case-insensitive)
// Added by Predicador
function limpiaCOM($cadena){
// Lista de palabras a eliminar
 $pal_a_eliminar = array("(arg)",
                        "[arg]",
                        "(BT)",
                        "[BT]",
                        "(com)",
                        "[com]",
                        "(DD)",
                        "[DD]",
                        "(doc)",
                        "[doc]",
                        "(documental)",
                        "[documental]",
                        "(documentales)",
                        "[documentales]",
                        "(drivers)",
                        "[drivers]",
                        "(dvd5)",
                        "[dvd5]",
                        "(ed2k)",
                        "[ed2k]",
                        "[esp]",
                        "(esp)",
                        "(game)",
                        "[game]",
                        "[inf]",
                        "(inf)",
                        "(infantiles)",
                        "[infantiles]",
                        "(juego)",
                        "[juego]",
                        "(juegos)",
                        "[juegos]",
                        "[lat]",
                        "(lat)",
                        "(libro)",
                        "[libro]",
                        "(manual)",
                        "[manual]",
                        "(obras)",
                        "[obras]",
                        "(otro)",
                        "[otro]",
                        "(otros)",
                        "[otros]",
                        "(P2M)",
                        "[P2M]",
                        "(pack)",
                        "[pack]",
                        "(PC)",
                        "[PC]",
                        "(ped)",
                        "[ped]",
                        "(PS2)",
                        "[PS2]",
                        "(soft)",
                        "[soft]",
                        "(software)",
                        "[software]",
                        "(url)",
                        "[url]",
                        "(varios)",
                        "[varios]",
                        "(vid)",
                        "[vid]",
                        "(video)",
                        "[video]",
                        "[xxx]",
                        "(xxx)");

 $cadenanueva = str_ireplace($pal_a_eliminar, "", $cadena);
 return trim($cadenanueva);
}

// Imprime el titulo de los caracteres
// Added by Predicador
function construyeLetra($cad){
 return "<br><br>\n   <span style=\"font-weight: bold\"><span style=\"font-size: 18px; line-height: normal\"><a name=\"_                                                                                   ".$cad."\">Letra ".$cad."</a></span></span>\n <br>\n";
}

// Función para conectarse con la base
function Conectarse($host, $usr, $pass, $bd){
 if (!($link=mysql_connect($host, $usr, $pass))){
   echo "Error conectando a la base de datos.";
   exit();
 }
 if (!mysql_select_db($bd,$link)){
   echo "Error seleccionando la base de datos.";
   exit();
 }
 return $link;
}

// Permite argumentos por consola
if ($argv){
 $_ARG = array();
 foreach ($argv as $arg){
  if (ereg('--([^=]+)=(.*)',$arg,$reg)){
   $_ARG[$reg[1]] = $reg[2];
  }
  elseif (ereg('-([a-zA-Z0-9])',$arg,$reg)) {
   $_ARG[$reg[1]] = 'true';
  }
 }
}

// Evaluo y proceso el parámetro "t"
if (isset($_GET["t"])){
 if (is_numeric($_GET["t"])){
  $intBusca = intval($_GET["t"]);
 }
}
elseif (isset($_ARG["t"])){
 if (is_numeric($_ARG["t"])){
  $intBusca = intval($_ARG["t"]);
 }
}
else {
 exit("¿Tratando de cagarme?<br> Por las dudas guardo tu IP.<br>"); // Solo para asustar
}
switch ($intBusca){
case 1 : $cadenaBusca = "(COM)";
break;
case 2 : $cadenaBusca = "(PED)";
break;
default : $cadenaBusca = "(COM)";
}

// Evaluo si se suministró el parámetro "f"
if (isset($_GET["f"])){
 if (is_numeric($_GET["f"])){
  $foro = intval($_GET["f"]);
 }
}
elseif (isset($_ARG["f"])){
 if (is_numeric($_ARG["f"])){
  $foro = intval($_ARG["f"]);
 }
}
else {
 exit("¿Tratando de cagarme?<br> Por las dudas guardo tu IP.<br>"); // Solo para asustar
}

// Evaluo si se suministró el parámetro "l"
if (isset($_GET["l"])){
 $letra = substr($_GET["l"],0,1);
}
elseif (isset($_ARG["l"])){
 $letra = substr($_ARG["l"],0,1);
}
else {
 // SMF 2.0.X
 $SQL = "SELECT t.id_topic, m.subject, m.poster_name, m.poster_time, m.modified_time, b.name FROM smf_messages m, smf_to                                                                                   pics t, smf_boards b WHERE t.locked=0 AND t.id_first_msg=m.id_msg AND m.id_board=b.id_board AND m.id_board='".$foro."' A                                                                                   ND subject LIKE '".$cadenaBusca."%'";
}

// Parsing del parámetro "l"
if ($letra){
 (preg_match("/[a-z0-9]/i", $letra));
 $letra = strtolower($letra);
 $archivo = $archivo . "_" . $letra;
 // SMF 2.0.X
 $SQL = "SELECT t.id_topic, m.subject, m.poster_name, m.poster_time, m.modified_time, b.name FROM smf_messages m, smf_to                                                                                   pics t, smf_boards b WHERE t.locked=0 AND t.id_first_msg=m.id_msg AND m.id_board=b.id_board AND m.id_board='".$foro."' A                                                                                   ND ((subject LIKE '".$cadenaBusca.$letra."%') OR (subject LIKE '".$cadenaBusca." ".$letra."%'))";
}

// Conexión a la base de datos
conectarse($db_server, $db_user, $db_passwd,$db_name) or exit("Se ha producido un error al conectarse");

// Pasar texto a UTF8 en caso de necesitarlo
if ($db_character_set == "utf8"){
 mysql_query ("SET NAMES 'utf8'") or exit("Se ha producido un error al ejecutar la consulta\n" .$SQL);
}

// Added by Predicador 25.01.2006
$rs = mysql_query($SQL) or exit("Se ha producido un error al ejecutar la consulta\n" .$SQL);

// Incializamos el array
$index = array();
while ($datos = mysql_fetch_array($rs)){
 // Separado el titulo en variable para poder filtrarlo y los demás por comodidad
 $titulo= limpiaCOM($datos['subject']);
 $topicId = $datos['id_topic'];
 $usuario = $datos['poster_name'];
 $boardName = $datos['name'];
 $postTime = $datos['poster_time']; // Se quitó el máximo. Added by elrosti
 $modifiedtime = $datos['modified_time']; // Added by elrosti
 // Se construye un arreglo con cada linea
 // Filtra vacíos. Added by Camello_AR
 if (rtrim($titulo) != ""){
  $index[] = array("titulo" => $titulo, "usuario" => $usuario, "topicId" => $topicId, "ptime" => $postTime, "mtime" => $                                                                                   modifiedtime); // Se agregó $modifiedtime al array. Added by elrosti
 }
}
mysql_free_result($rs);
mysql_close();

// Ordenemos el arreglo por titulo
foreach($index as $temporal)
$AuxArray[] = strtolower($temporal['titulo']);
array_multisort($AuxArray, SORT_ASC, $index);

// Abrimos el archivo
// Si ya existe se destruye
$archivo = $archivo . $foro . ".html";
if (!($handle = fopen($archivo, "w"))){
 exit($archivo . " no es escribible, fijate los permisos.");
}

// Encabezado javascript y función de filtro
fwrite($handle, "<script type=\"text/javascript\">
function showWait()
{
 document.getElementById('pleasewaitScreen').style.display=\"\";
}
function hideWait()
{
 document.getElementById('pleasewaitScreen').style.display=\"none\";
}
function filternonew()
{
 var list=document.getElementsByTagName(\"div\");
 for (i=0;i<list.length;i++)
 {
  if (list[i].className=='nn')
      list[i].style.display=(list[i].style.display =='none')?'':'none';
 }
 hideWait();
}
</script>");

// Información del foro y fecha que se construyó el indice
fwrite($handle, "   <span style=\"font-size: 18px; line-height: normal; font-weight: bold\">Indice de links del foro:                                                                                      ". $boardName ."<br></span>\n
   <span style=\"font-size: 10px; line-height: normal\">Generado el ". date("d.m.y  \a\ \l\a\s H:i:s")."</span><br><br>\                                                                                   n");

// Filtro de todos o solo nuevos-actualizados
fwrite($handle, "Mostrar <select onchange=\"window.setTimeout('showWait(),50');window.setTimeout('filternonew()',100);\"                                                                                   >
  <option value=\"all\" selected>todos los posts</option>
  <option value=\"new\">solo los nuevos y actualizados</option>
 </select><br><br> \n
 <div id=\"pleasewaitScreen\" style=\"background:#ff0000;color:#ffffff;position:absolute;z-index:5;top:50%;left:42%;padd                                                                                   ing:10px;display:none;\">Procesando!!!</div>\n" );

// Información en fechas de los post nuevos y actualizados
fwrite($handle, "   <span style=\"font-size: 12px; line-height: normal\">Las lineas marcadas con <img src=/list/new.png>                                                                                    corresponden a posts nuevos entre el ". date("d.m.y", (time() - ($diasNuevo * 24 * 60 * 60)))." y el ". date("d.m.y") .                                                                                   ".<br>
Las lineas marcadas con <img src=/list/updated.png> corresponden a posts actualizados entre el ". date("d.m.y", (time()                                                                                    - ($diasNuevo * 24 * 60 * 60)))." y el ". date("d.m.y") .".<br>Las lineas marcadas con <img src=/list/hd.png> <img src=/                                                                                   list/hd1080.png> <img src=/list/hd720.png> corresponden a material en Alta Definición</span><br><br>\n");

// Imprimimos el indice en un archivo
if (!$letra){
 $indice = "<a href=\"#_#\" class=\"postlink\">#</a> <a href=\"#_[\">[</a> ";
 for ($le = 65; $le <= 90; $le++) // de A a Z
 $indice .= "<a href=\"#_".chr($le)."\">".chr($le)."</a> ";
 fwrite($handle,$indice);
}

// Imprimimos los titulos y sus links
$cantPelis = 0;
$cantPelisNew = 0;
$cantPelisMod = 0;
$ultimaLetra = "";
$letraActual  = "";
$fueNumero = false;
$timewindow = (time() - ($diasNuevo * 24 * 60 * 60));
foreach ($index as $data){
 $oneline = "";
 $letraActual = strtolower(substr($data['titulo'], 0, 1));
 if ($ultimaLetra != $letraActual){
  // Reemplazar por un preg_match("/[0-9]/", $letraActual)
  if (($letraActual <= "9") and !($fueNumero)){
    $oneline .= construyeLetra("#");
    $fueNumero = true;
  }
  else{
   // Reemplazar por un preg_match("/[a-z]/i", $letraActual)
   if (( "a" <= $letraActual) and ($letraActual <= "z")){
    $oneline .= construyeLetra(strtoupper($letraActual));
   }
   else{
    if ( $letraActual == "["){
     $oneline .= construyeLetra(strtoupper($letraActual));
    }
   }
  }
 }
 $cantPelis = $cantPelis + 1;
 $isNovedad = $data['ptime'] >= $timewindow;

 // $isModified es válido si (mtime inside $timewindow) y si la modificación tiene lugar al menos un día después de la f                                                                                   echa de creación
 $isModified = ($data['mtime'] >= $timewindow) && ($data['mtime'] >= ($data['ptime'] + 86400)) ? true : false; // 86400                                                                                    = 60 * 60 * 24 :: o sea, 1 día en segundos

 // Probamos si es un enlace nuevo o no
 if ($isNovedad || $isModified){
  if ($isModified){
   $cantPelisMod = $cantPelisMod + 1;
   $oneline .= "     <img src=/Smileys/argentos/icon_arrow.gif> <img src=/list/updated.png> ";
  }
  else{
   $cantPelisNew = $cantPelisNew + 1;
   $oneline .= "     <img src=/Smileys/argentos/icon_arrow.gif> <img src=/list/new.png> ";
   // Aquí las cosas nuevas se guardan en un txt para ser leídas desde IRC
   // Descomentar para generar
   // $ircnov .= "Novedad ED2K: ". $data['titulo'] ." posteada por ". $data['usuario']. ". Link: http://www.argentop2p.n                                                                                   et/index.php?topic=". $data['topicId']."\n";
  }
 }
 else{
  $oneline .= "     <div class=\"nn\"><img src=/Smileys/argentos/icon_arrow.gif> ";
 }
 // Escribir el enlace
 $data['titulo'] = add_HD($data['titulo']);
 $anio = recupera_anio($data['titulo']);
 // Parsing del parametro "a" para generar lista con año al inicio
 if (isset($_GET["a"])){
  $oneline .= "(". $anio['anio'] .") <a href=http://www.argentop2p.net/index.php?topic=". $data['topicId'] ." target=_bl                                                                                   ank>". $anio['linea'] ."</a><em> - (". $data['usuario'] .")</em><br>";
 }
 elseif (isset($_ARG["a"])){
  $oneline .= "(". $anio['anio'] .") <a href=http://www.argentop2p.net/index.php?topic=". $data['topicId'] ." target=_bl                                                                                   ank>". $anio['linea'] ."</a><em> - (". $data['usuario'] .")</em><br>";
 }
 else {
 $oneline .= "<a href=http://www.argentop2p.net/index.php?topic=". $data['topicId'] ." target=_blank>". $data['titulo']                                                                                    ."</a><em> - (". $data['usuario'] .")</em><br>";
 }
 // Solo cerrar el tag DIV si no es algo nuevo o modificado
 if (!($isNovedad || $isModified)){
  $oneline .= "</div>";
 }
 $oneline .= "\n ";

 // Escribir la línea generada
 fwrite($handle, $oneline);
 $ultimaLetra = $letraActual;
}

// Pie y resumen del indice
fwrite($handle, "<br><br><span style=\"font-weight: bold\">:.:: Posts nuevos: ". $cantPelisNew ."<br>
:.:: Post modificados: ". $cantPelisMod ."<br>
:.:: Total de posts publicados: ". $cantPelis ."<br></span><br>\n
<span style=\"font-size: 10px; line-height: normal\">::.: IndexA version ". $version ." by ArgentoP2P.net Coders Team ::                                                                                    2005-2014. </span>\n");
fclose($handle);

// Escribir el archivo TXT si hay un archivo y foro 5 (ed2k en argentop2p).
if ($irctxt != ""){
 if ($foro == 5 || foro == 31) {
  $irctxt .= $foro . ".txt";
  if ($handle = fopen($irctxt, "w")){
    fwrite($handle,$ircnov);
    fclose($handle);
  }
  else {
   exit($irctxt . " no es escribible, fijate los permisos.");
  }
 }
}

$seg2 = microtime(true);
$segs = $seg2-$seg1;
echo "El proceso se ha completado sin errores.<br>";
echo "Indice del foro ". $boardName . " generado en $segs segundos";

// Función recupera año
function recupera_anio ($linea){
 $expresion = '/(\(|\[|\-)\d{4}(\)|\]|\-)/';
 preg_match($expresion, $linea, $aniot);
 $salida['anio'] = substr("$aniot[0]",1,4);
 //FIXME da advertencias cuando $aniot[0] es nulo
 $salida['linea'] = str_replace("$aniot[0]","",$linea);
 // Si no tiene año asume ####
 if ($salida['anio']==""){
  $salida['anio']="####";
 }
 return $salida;
}

// Función que reemplaza etiquetas hd de texto por imágenes correspondiente. Added by Camello_AR
// Requiere entrada la linea que desea procesarse
// Salida, Variable de Cadena
function add_HD ($linea){
 $hd = array(   "*hd*",
                "(hd)",
                "[hd]",
                "*720p*",
                "(720p)",
                "[720p]",
                "*1080p*",
                "(1080p)",
                "[1080p]");
 $img_hd = array(       "<img src=/list/hd.png>",
                        "<img src=/list/hd.png>",
                        "<img src=/list/hd.png>",
                        "<img src=/list/hd720.png>",
                        "<img src=/list/hd720.png>",
                        "<img src=/list/hd720.png>",
                        "<img src=/list/hd1080.png>",
                        "<img src=/list/hd1080.png>",
                        "<img src=/list/hd1080.png>");
 $linea = str_ireplace($hd,$img_hd,$linea,$tot);
 return $linea;
}
?>

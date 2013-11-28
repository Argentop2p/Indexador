<?
/*
-----------------------------------------------------

Modulo: Script Indexador
Descripcion: Indexar los posts, con TAGs.
Entrada: f= forum number; t= que buscar (1:(COM) - 2: (PED)); l= (letra)  [l es un parametro opcional]
         ie: indexa_html.php?f5&t=1&l=d

Autor: snoop852@gmail.com ( para argentop2p.net -ex argentop2p.com.ar-)
Fecha: 08/12/05 -
* Modificado completamente por Predicador (25.01.2006)
* Agregados por elrosti, DAX y Camello_AR

19.02.09 - Agregado de filtro de nuevos y muestra de feedback
20.02.09 - Agregado que ponga como nuevos los posts modificados.
		   No indexa los posts cerrados.
26.02.09 - Distingue entre post nuevos y modificados
		   Minima optimizacion
28.02.09 - Modficados nombres de campos por migracion a SMF 2.0 RC1
02.03.09 - Soporte para UTF8
04-10-10 - Agregada compatiblidad con PHP 5.3 (DAX)
03-08-12 - Agregado control de tiempo de ejecuci�n y año de material (Camello_AR)
27-11-13 - Agregado imagenes de HD (Camello_AR)

-----------------------------------------------------
Por favor, no cambien los copyleft correspondientes.
Bajo Licencia GPL v2
http://www.gnu.org/licenses/gpl.txt
-----------------------------------------------------
*/
$seg1 = microtime(true);

$version = "1.4a beta 2.";

# nombre del archivo de salida
# se complementa con el numero del foro y luego .html o _x.html siendo x una letra.
# ejemplo: para foro 5 y letra a (indexa_html.php?f5&t=1&l=a)  sera: listado5_a.html
$archivo = "listado";
# nombre del archivo con las novedades solas en formato TXT (no poner nombre para que no se genere)
$irctxt = "../novedades";
$ircnov = "";

# Por cuantos dias algo se considera nuevo.
$diasNuevo = "7";

# Variables de la DB
# $db_server,  $db_user,  $db_passwd, $db_name
require_once('../Settings.php');


// Funcion que elimina ciertas palabras del una cadena (case-insensitive)
// added by Predicador
function limpiaCOM($cadena)
{
 // Lista de palabras a eliminar
        $pal_a_eliminar = array("(url)",
				"[url]",
                                "(ped)",
				"[ped]",
                                "(com)",
                                "[com]",
                                "(arg)",
                                "[arg]",
                                "(documental)",
				"[documental]",
                                "[vid]",
                                "(vid)",
                                "[doc]",
                                "(doc)",
                                "(pack)",
                                "[pack]",
                                "[lat]",
				"(lat)",
                                "[esp]",
				"(esp)",
                                "[xxx]",
				"(xxx)",
                                "[inf]",
				"(inf)",
                                "[ed2k]",
                                "(ed2k)",
                                "[BT]",
                                "(BT)",
                                "[DD]",
                                "(DD)",
                                "(juego)",
                                "[juego]",
                                "(juegos)",
                                "[juegos]",
                                "(game)",
                                "[game]",
                                "(P2M)",
                                "[P2M]",
                                "(PC)",
				"[PC]",
                                "(PS2)",
				"[PS2]",
				"(soft)",
				"[soft]",
                                "(software)",
                                "[software]",
				"(otros)",
				"[otros]",
                                "(otro)",
                                "[otro]",
				"(video)",
				"[video]",
				"(varios)",
				"[varios]",
                                "(infantiles)",
                                "[infantiles]",
                                "(obras completas)",
                                "[obras completas]",
                                "(libro)",
                                "[libro]",
                                "(manual)",
                                "[manual]",
                                "(dvd5)",
                                "[dvd5]",
                                "(drivers)",
                                "[drivers]", 
                                "[documentales]",
                                "(documentales)");

 //foreach ($pal_a_eliminar as $una_palabra)
 //   $cadena = preg_replace($una_palabra, "", $cadena);

 // Solo funciona en php 5.0 y es mas eficiente
 $cadenanueva = str_ireplace($pal_a_eliminar, "", $cadena);
 return trim($cadenanueva);
}

// Imprime el titulo de los caracteres
// added by Predicador
function construyeLetra($cad){
  return "<br><br>\n   <span style=\"font-weight: bold\"><span style=\"font-size: 18px; line-height: normal\"><a name=\"_".$cad."\">Letra ".$cad."</a></span></span>\n <br>\n";
}

/* Funcion para conectarse con la base */
function Conectarse($host, $usr, $pass, $bd)
{
   if (!($link=mysql_connect($host, $usr, $pass)))
   {
      echo "Error conectando a la base de datos.";
      exit();
   }
   if (!mysql_select_db($bd,$link))
   {
      echo "Error seleccionando la base de datos.";
      exit();
   }
   return $link;
}

# Parsing de los parametros recibidos
if (is_numeric($_GET["t"])) 
{
        $intBusca = intval($_GET["t"]);
        switch ($intBusca)
        {
                case 1 : $cadenaBusca = "(COM)";
                        break;
                case 2 : $cadenaBusca = "(PED)";
            break;
                default : $cadenaBusca = "(COM)";
   }
} 
else 
{
        exit("Tratando de cagarme?<br> Por las dudas guardo tu IP.<br>");  # solo para asustar
}

if (is_numeric($_GET["f"])) 
{
        $foro = intval($_GET["f"]);
} 
else 
{
        exit("Tratando de cagarme?<br> Por las dudas guardo tu IP.<br>");  # solo para asustar
}
# Fin parsing de parametros recibidos

#*******************************************
#              EXPERIMENTAL
# Si anda bien luego se embellece el codigo y se hace mas eficiente
# En fin, es el parsing y armado de query si es que pide por una letra, sino normal
#*******************************************
$letra = substr($HTTP_GET_VARS["l"],0,1);
if (preg_match("/[a-z0-9]/i", $letra))
{  
    # si hay una letra o numero
    # este MySQL no soporta expresiones regulares, asi que a hacer las cosas a mano LPM!
    # $condicion = "REGEX '[(]com[)]([[:space:]]*)".$letra."'";
    $letra = strtolower($letra);
    $archivo = $archivo . "_" . $letra;
	
	# SMF 2.0 RC1
	# Se modifican los campos posterName => poster_name, posterTime => poster_time
    $SQL =  "SELECT t.id_topic, t.id_board, t.id_first_msg, t.id_member_started, m.id_msg, m.subject, m.id_topic, m.id_board, m.id_member, m.poster_name, m.poster_time, z.id_board, z.name ";        
    $SQL = $SQL . "FROM smf_messages m, smf_topics t, smf_boards z WHERE t.locked=0 AND t.id_member_started=m.id_member AND m.id_board=z.id_board AND t.id_board='".$foro."' ";
    $SQL = $SQL . "AND ((subject LIKE '".$cadenaBusca.$letra."%') OR (subject LIKE '".$cadenaBusca." ".$letra."%'))";
	# SMF 1.1 RC2
    #$SQL =  "SELECT t.id_topic, t.id_board, t.id_first_msg, t.id_member_started, m.id_msg, m.subject, m.id_topic, m.id_board, m.id_member, m.posterName, m.posterTime, z.id_board, z.name ";        
    #$SQL = $SQL . "FROM smf_messages m, smf_topics t, smf_boards z WHERE t.locked=0 AND t.id_member_started=m.id_member AND m.id_board=z.id_board AND t.id_board='".$foro."' ";
    #$SQL = $SQL . "AND ((subject LIKE '".$cadenaBusca.$letra."%') OR (subject LIKE '".$cadenaBusca." ".$letra."%'))";
	
	# phpBB 2.0.19 version:
	# $SQL =  "SELECT y.user_id, y.username, x.topic_title, x.topic_id, x.forum_id, x.topic_poster ";
    # $SQL = $SQL . "FROM phpbb_topics x, phpbb_users y WHERE y.user_id=x.topic_poster AND forum_id='".$foro."'";
    # $SQL = $SQL . "AND ((topic_title LIKE '".$cadenaBusca.$letra."%') OR (topic_title LIKE '".$cadenaBusca." ".$letra."%'))";
} 
else 
{
    # SMF 2.0 RC1
    $SQL = "SELECT t.id_topic, m.subject, m.poster_name, m.poster_time, m.modified_time, b.name FROM smf_messages m, smf_topics t, smf_boards b WHERE t.locked=0 AND t.id_first_msg=m.id_msg AND m.id_board=b.id_board AND m.id_board='".$foro."' AND subject LIKE '".$cadenaBusca."%'";
	# SMF 1.1 RC2
	#$SQL = "SELECT t.id_topic, m.subject, m.posterName, m.posterTime, m.modifiedTime, b.name FROM smf_messages m, smf_topics t, smf_boards b WHERE t.locked=0 AND t.id_first_msg=m.id_msg AND m.id_board=b.id_board AND m.id_board='".$foro."' AND subject LIKE '".$cadenaBusca."%'";
	# phpBB 2.0.19 version:
    # $SQL="SELECT y.user_id, y.username, x.topic_title, x.topic_id, x.forum_id, x.topic_poster FROM phpbb_topics x, phpbb_users y WHERE y.user_id=x.topic_poster AND forum_id='".$foro."' AND topic_title LIKE '".$cadenaBusca."%'";
}


#*******************************************
#           FIN  EXPERIMENTAL
#*******************************************


# Conexion a la base de datos
conectarse($db_server, $db_user, $db_passwd,$db_name) or exit("Se ha producido un error al conectarse");
   if ($db_character_set == "utf8") {
     # if it's UTF8 then preapare to it ;)
      mysql_query ("SET NAMES 'utf8'") or exit("Se ha producido un error al ejecutar la consulta\n" .$SQL);
   }


# ---------------------- Comienzo codigo nuevo -------------------------
# Agregado por Predicador el 25.01.2006

 $rs = mysql_query($SQL) or exit("Se ha producido un error al ejecutar la consulta\n" .$SQL);

 // incializamos el array
 $index = array();

while ($datos = mysql_fetch_array($rs)) 
{
    // separado el titulo en variable para poder filtrarlo y los demas por comodidad
    $titulo= limpiaCOM($datos['subject']);
    $topicId = $datos['id_topic'];
    $usuario = $datos['poster_name'];
    $boardName = $datos['name'];
    $postTime = $datos['poster_time']; // QUITE EL MAX - ELROSTI
    $modifiedtime = $datos['modified_time']; // NUEVO - ELROSTI
    // se construye un arreglo con cada linea
	if (rtrim($titulo) != "") { //FILTRA VACIOS inicia IF [ propuesto por Camello_AR ]
       $index[] = array("titulo" => $titulo, "usuario" => $usuario, "topicId" => $topicId, "ptime" => $postTime, "mtime" => $modifiedtime); // AGREGUE $modifiedtime AL ARRAY - ELROSTI
    }  //fin IF FILTRA VACIOS

}
mysql_free_result($rs);
mysql_close();

 // ordenemos el arreglo por titulo
foreach($index as $temporal)
        $AuxArray[] = strtolower($temporal['titulo']);
array_multisort($AuxArray, SORT_ASC, $index);

# Abrimos el archivo
# Si ya existe se destruye
$archivo = $archivo . $foro . ".html";
if ($handle = fopen($archivo, "w"))
{
    //exit("Error groso al abrir el archivo.");
} else
{
    exit($archivo . " no es writable, fijate los permisos.");
}
//}
// Generamos el header del HTML


# fwrite($handle, "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\"\n         \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n");
# fwrite($handle, "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\">\n");
# fwrite($handle, "<head>\n  <meta http-equiv=\"content-type\" content=\"text/html; charset=iso-8859-1\" />\n  <meta http-equiv=\"Pragma\" content=\"no-cache\" />\n");
# fwrite($handle, "  <meta content=\"no-cache\" http-equiv=\"no-cache\" />\n  <meta http-equiv=\"Cache-Control\" content=\"no-cache\" /> ");
# fwrite($handle, "  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">\n  <meta http-equiv=\"Content-Style-Type\" content=\"text/css\">\n");
# fwrite($handle, "  <title>ARGENTOP2P :: Indexer</title>\n  <link rel=\"stylesheet\" href=\"templates/subSilver/subSilver.css\" type=\"text/css\">\n</head>\n<body>\n");
fwrite($handle, "<script type=\"text/javascript\">
function showWait()
{
 //document.all.pleasewaitScreen.style.pixelTop = (document.body.scrollTop + 50);
 document.getElementById('pleasewaitScreen').style.display=\"\";
}	
function hideWait()
{
  document.getElementById('pleasewaitScreen').style.display=\"none\";
}
function filternonew()
{
 var list=document.getElementsByTagName(\"div\");
 for (i=0;i<list.length;i++){
    if (list[i].className=='nn')
      list[i].style.display=(list[i].style.display =='none')?'':'none';
 }
 hideWait();
}
</script>");


fwrite($handle, "   <span style=\"font-size: 18px; line-height: normal; font-weight: bold\">Indice de links del foro:   ". $boardName ."<br></span>\n
   <span style=\"font-size: 10px; line-height: normal\">Generado el ". date("d.m.y  \a\ \l\a\s H:i:s")."</span><br><br>\n");

fwrite($handle, "Mostrar <select onchange=\"window.setTimeout('showWait(),50');window.setTimeout('filternonew()',100);\">
  <option value=\"all\" selected>todos los posts</option> 
  <option value=\"new\">solo los nuevos y actualizados</option> 
 </select><br><br> \n
 <div id=\"pleasewaitScreen\" style=\"background:#ff0000;color:#ffffff;position:absolute;z-index:5;top:50%;left:42%;padding:10px;display:none;\">Procesando!!!</div>\n" );

fwrite($handle, "   <span style=\"font-size: 12px; line-height: normal\">Las lineas marcadas con <img src=/list/new.png> corresponden a posts nuevos entre el ". date("d.m.y", (time() - ($diasNuevo * 24 * 60 * 60)))." y el ". date("d.m.y") .".<br>
Las lineas marcadas con <img src=/list/updated.png> corresponden a posts actualizados entre el ". date("d.m.y", (time() - ($diasNuevo * 24 * 60 * 60)))." y el ". date("d.m.y") .".<br>Las lineas marcadas con <img src=/list/hd3.png> <img src=/list/hd1080.png> <img src=/list/hd720.png> corresponden a material en Alta Definici&oacute;n</span><br><br>\n");


// imprimimos el Indice en un archivo
if (!$letra) {
        $indice = "<a href=\"#_#\" class=\"postlink\">#</a> <a href=\"#_[\">[</a> ";
        for ($le = 65; $le <= 90; $le++) // de A a Z
                $indice .= "<a href=\"#_".chr($le)."\">".chr($le)."</a> ";
    fwrite($handle,$indice);
}


 // imprimimos las peliculas y sus links
 $cantPelis = 0;
 $cantPelisNew = 0;
 $cantPelisMod = 0;
 $ultimaLetra = "";
 $letraActual  = "";
 $fueNumero = false;
 $timewindow = (time() - ($diasNuevo * 24 * 60 * 60));
 foreach ($index as $data) 
 {
    $oneline = "";
	$letraActual = strtolower(substr($data['titulo'], 0, 1));
    if ($ultimaLetra != $letraActual) 
    {
        // reemplazar por un preg_match("/[0-9]/", $letraActual)
        if (($letraActual <= "9") and !($fueNumero)) 
        {
            $oneline .= construyeLetra("#");
            $fueNumero = true;
        } 
        else 
        {
            // reemplazar por un preg_match("/[a-z]/i", $letraActual)
            if (( "a" <= $letraActual) and ($letraActual <= "z")) 
            {
                $oneline .= construyeLetra(strtoupper($letraActual));
            }
            else 
            {
                if ( $letraActual == "[") 
                {
                    //$packActual = strtolower(substr($data[titulo], 0, 4));
                    $oneline .= construyeLetra(strtoupper($letraActual));
                }
            }
        }
    }
    $cantPelis = $cantPelis + 1;
	$isNovedad = $data['ptime'] >= $timewindow;
	// $isModified is true if (mtime inside $timewindow) AND (the modification happens atleast one day after the creation)
	$isModified = ($data['mtime'] >= $timewindow) && ($data['mtime'] >= ($data['ptime'] + 86400)) ? true : false; // 86400 = 60 * 60 * 24 :: o sea, 1 dia en segundos
    
	// here we test if it's a new link or not ;)	
	if ($isNovedad || $isModified) {
		if ($isModified) { //
			$cantPelisMod = $cantPelisMod + 1;
			$oneline .= "     <img src=/Smileys/argentos/icon_arrow.gif> <img src=/list/updated.png> ";
		}else{
		    $cantPelisNew = $cantPelisNew + 1;
		    $oneline .= "     <img src=/Smileys/argentos/icon_arrow.gif> <img src=/list/new.png> ";
		    # here the new stuff is stored to be written to a TXT file to read from IRC
		    $ircnov .= "Novedad ED2K: ". $data['titulo'] ." posteada por ". $data['usuario']. ". Link: http://www.argentop2p.net/index.php?topic=". $data['topicId']."\n";
		}
	}else {
		$oneline .= "     <div class=\"nn\"><img src=/Smileys/argentos/icon_arrow.gif> ";
	}
	
	// write the link
	$anio = recupera_anio($data['titulo']);
	$data['titulo'] = add_HD($data['titulo']);
	$oneline .= "<a href=http://www.argentop2p.net/index.php?topic=". $data['topicId'] ." target=_blank>". $data['titulo'] ."</a><em> - (". $data['usuario'] .")</em><br>";
	//$oneline .= "<a href=http://www.argentop2p.net/index.php?topic=". $data['topicId'] ." target=_blank>". $data['titulo'] ."</a><em> - (". $data['usuario'] .")</em>; de $anio[anio]<br>";
	// only close the DIV if it isn't new nor modified
	if (!($isNovedad || $isModified)) {
		$oneline .= "</div>";
	}
	$oneline .= "\n ";
	
	# write the composed line
	fwrite($handle, $oneline);
    $ultimaLetra = $letraActual;
 }
 fwrite($handle, "<br><br><span style=\"font-weight: bold\">:.:: Posts nuevos: ". $cantPelisNew ."<br>
 :.:: Post modificados: ". $cantPelisMod ."<br>
 :.:: Total de posts publicados: ". $cantPelis ."<br></span><br>\n
 <span style=\"font-size: 10px; line-height: normal\">::.: IndexA version ". $version ." by ArgentoP2P.net Coders Team :: 2005-2012. </span>\n");
  fclose($handle);
 
 // just write the TXT if there's a file and forum is 5 (ed2k en argentop2p). 
 if ($irctxt != ""){
    if ($foro == 5 || foro == 31) {
	   $irctxt .= $foro . ".txt";
	   if ($handle = fopen($irctxt, "w")){
          fwrite($handle,$ircnov);
          fclose($handle);
       } else {
       exit($irctxt . " no es writable, fijate los permisos.");
       }
	}
 }
 $seg2 = microtime(true);
# ---------------------- Fin codigo nuevo -------------------------
 $segs = $seg2-$seg1;
 echo "El proceso se ha completado sin errores.<br>";
 echo "Indice del foro ". $boardName . " generado en $segs segundos";

 

function recupera_anio ($linea) {
	$expresion = '/(\(|\[|\-)\d{4}(\)|\]|\-)/';
	preg_match($expresion, $linea, $anio);
	$salida[anio] = substr($anio[0],1,4);
	$salida[linea] = str_replace($anio[0],"",$linea);
	//Si no tiene año, asume 9999 para enviarlo al fondo de tabla
	if ($salida[anio]=="") {
		$salida[anio]=9999;
	}
	return $salida;
}
/*
FUNCION que Elimina etiquetas HD y las reemplaza por la imagen correspondiente - Camello_AR 2013
Requiere entrada la linea que desea procesarse
Salida, Variable de Cadena
*/
function add_HD ($linea) {
	$hd = array("[hd]","(hd)","*720p*","(720p)","*1080p*","(1080p)");
	$img_hd = array("<img src=/list/hd.png>","<img src=/list/hd.png>","<img src=/list/hd720.png>","<img src=/list/hd720.png>","<img src=/list/hd1080.png>","<img src=/list/hd1080.png>");
	$linea = str_ireplace($hd,$img_hd,$linea,$tot);
	return $linea;
}
?>

<?php
/**
 * Fogadja és kiértékeli a megoldásokat
 * Az adatokat POST-ben kapja:
 * $_POST['source_code']: a megoldás fárráskodja
 * $_POST['language']: milyen nyelven lett írna
 * $_POST['solution_id']: egyedi azonosító a megoldáshoz
 * $_POST['challenge_id']: melyik feladatra érkezett a megoldás  
 */
 

/*$_POST['source_code'] = "#include <stdio.h>
#include <math.h>
#include <stdlib.h>

int main()
{
    printf(\"world hello\\nmegforditani kell ezt\\n\");
    
    return 0;
}";*/

/*
$_POST['source_code'] = '<?php

fscanf(STDIN, "%d\n", $number);
for ($case = 0;$case < $number;$case++) { 
    $line = trim(fgets(STDIN));
    # $test represents the test case, do something with it
    $words = explode(\' \', $line);
    for ($i = count($words) - 1;$i >= 0;$i--) {
        echo trim($words[$i]);
        if ($i == 0) {
            echo "\n";
        } else {
            echo \' \';
        }
    }
}';

$_POST['language'] = 'php';
$_POST['solution_id'] = '63';
$_POST['challenge_id'] = '2';
*/

include('ArrayToXML.php');

/**
 * Segít kiszámolni a futás közben eltelt idõt
 */
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

$MAX_TIME = 5; //másodperc
$challenge_id = is_numeric($_POST['challenge_id']) ? $_POST['challenge_id'] : 0;
$solution_id = is_numeric($_POST['solution_id']) ? $_POST['solution_id'] : 0;
$source_code = $_POST['source_code'];

//Forráskód lementése
if ($solution_id > 0 && count($source_code) > 0) {
    mkdir('./solutions/' . $solution_id);
    file_put_contents('solutions/' . $solution_id . '/source_code.' . $_POST['language'], $source_code);
} else {
    echo 'Bad requrest';exit;
}

//Ebben átroljuk a teszt eredményét
$result = array();

//Feladatok megoldása esessével, végignézzük az összes tesztesetre
$i = 1;
$delete_files = array();

//Átmásoljuk egy ideiglenes könyvtárba a forráskódot, hogy onnan fusson
$delete_files[] = $tmp_sorce_code = tempnam('tmp/', 'source_code_');
copy('solutions/' . $solution_id . '/source_code.' . $_POST['language'], $tmp_sorce_code);

//Abban az esetben, ha kell lefordítjuk a kódot
$delete_files[] = $tmp_executable = tempnam('tmp/', 'executable_');
$delete_files[] = $tmp_error_log = tempnam('tmp/', 'error_log_');
switch ($_POST['language']) {
    case 'c' : system('timeout 3 gcc -x c ' . $tmp_sorce_code . ' -o ' . $tmp_executable . ' -lm 2> ' . $tmp_error_log);
        break;
}
$error_log = file_get_contents($tmp_error_log);
$error_log = str_replace(array($tmp_sorce_code), array('solution.' . $_POST['language']), $error_log);
file_put_contents($tmp_error_log, $error_log);
while (file_exists($file = 'challenges/' . $challenge_id . '/input' . $i . '.txt'))
{
    //Átmásoljuk a szükséges file-okat egy ideiglenes könyvtárba, hogy csak ehhez kelljen hozzáférnie
    $tmp_input_name = tempnam('tmp/', 'input_');
    $tmp_output_name = str_replace('input', 'output', $tmp_input_name);
    copy($file, $tmp_input_name);
    
    //Elkezdjük mérni a futáshoz szükséges idõt
    $time_start = microtime_float();
    //Lefutattjuk az i-ik tesztesetre
    switch ($_POST['language']) {
        case 'php' : system('timeout ' . $MAX_TIME . ' /var/www/checker/execute_solution.php ' . $tmp_sorce_code . ' < ' . $tmp_input_name . ' > ' . $tmp_output_name);
            break;
        case 'c'   : system('timeout ' . $MAX_TIME . ' /var/www/checker/execute_solution.sh ' . $tmp_executable . ' < ' . $tmp_input_name . ' > ' . $tmp_output_name);
            break;
    }
    //Kiszámítjuk mennyi ideig futott a script
    $time_end = microtime_float();
    
    //Ellenõrizzük, hogy jó-e a kimenet és lementjük
    $excepted = file_get_contents('challenges/' . $challenge_id . '/output' . $i .'.txt');
    $actual = file_get_contents($tmp_output_name);
    //Futási idõ kiszámítása
    $time = $time_end - $time_start;
    $node = array(
        'execute_time' => $time,
        'test_case' => 'input' . $i
    );
    if (strcmp($excepted, $actual) == 0) {
        $node['result'] = 'success';
    } else {
        $node['result'] = 'wrong output';
    }
    if ($time > $MAX_TIME) {
        $node['result'] = 'timeout';
    }
    $result['input' . $i] = $node;
    //Kitöröljük az ideiglenes file-okat
    unlink($tmp_input_name);
    unlink($tmp_output_name);
    //Mennyi memóriát használt, kbyte-ban
    //$memory = memory_get_usage() / 1024;
    $i++;
}
//Kitöröljük az ideiglenes forráskód file-t
foreach ($delete_files as $file) {
    unlink($file);
}
//Lementjük egy xml-ben a futás eredményét, hogy le lehessen kérdezni
file_put_contents('./solutions/' . $solution_id . '/result.xml', ArrayToXML::toXml($result));
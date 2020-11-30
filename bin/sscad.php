#!/usr/bin/php
<?php

if(eregi('\.scad$',$argv[1]) && is_file($argv[1]))
  $scad_src_file_name = $argv[1];
else
  exit("USAGE: sscad <scad_file_name>\n\n");

//echo $scad_src_file_name."\n";

$scad_src_file_content = file($scad_src_file_name);
unset($header_buffer);
$header_stop = 0;
$counter = 0;
unset($path);
foreach($scad_src_file_content as $l){

  if(!$header_stop)
    $header_buffer[] = $l;
  
  if(eregi('^[ ]*_([a-z][a-z0-9_]*)_stl[ ]*\("([a-z][a-z0-9_]*)"',$l,$matches)){
    
    $header_stop = 1;
    
    $module_name=$matches[1];
    $part_name=$matches[2];
    $n = strpos($l,'_')/2;
    $path[$n] = array("module"=>$module_name,"part"=>$part_name);
    
    //echo $n." ".$module_name.'/'.$part_name."\n";
    
    $buffer = $header_buffer;
    unset($buffer[sizeof($buffer)-1]);

    for($i=0;$i<=$n;$i++){
      $buffer_line='';
      for($j=0;$j<$i;$j++)
        $buffer_line .= "  ";
      $buffer_line .= '_'.$path[$i]["module"].'_stl("'.$path[$i]["part"].'"'.(($i==$n)?'':',scafold=true').')'.(($i==$n)?';':'')."\n";
      $buffer[] = $buffer_line;
    }

    $counter++;

    $scad_part_file_name = '_'.sprintf('%03d',$counter).'_'.$part_name.'.scad';

    if(!is_file($scad_part_file_name)){
      echo $scad_part_file_name."\n";
      file_put_contents($scad_part_file_name,$buffer);
    }

  }
   
}

mkdir("_stl_");

$scad_part_files = glob('_*_*.scad');
foreach($scad_part_files as $scad_part_file_name){
  
  $stl_file_name = eregi_replace('\.scad$','.stl',$scad_part_file_name);

  if(!is_file($stl_file_name)){
    
    exec("ps ahxwwo pid,command|grep openscad",$output);
    if(sizeof($output)>10)
      sleep(1);

    $pid = pcntl_fork();

    if($pid==-1) die('ERROR: could not fork');
    else if($pid){
      //pcntl_wait();
    }
    else{

      echo $stl_file_name."\n";
      $cmd = "openscad -q -o _stl_/$stl_file_name $scad_part_file_name";
      exec($cmd);

      exit;

    }
    
  }

}

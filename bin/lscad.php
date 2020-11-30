#!/usr/bin/php
<?php

$module_names_stl = array();
$module_names_dxf = array();

$module_name = '';
$module_name_nominal = '';

///////////////////////////////////////////////////////////////////////////////////////////////////////////

$cwd = getcwd();
$cwd_elements = explode('/',$cwd);

$index_file_name = '_'.$cwd_elements[sizeof($cwd_elements)-1].'_.scad.h';
unset($index_buffer);

if(sizeof($argv)==1){
  
  foreach(glob('*.stl.h') as $f)
    unlink($f);

  foreach(glob('*.scad') as $scad_file_name){
    $scad_file_name_elements = pathinfo($scad_file_name);
    if(is_file($scad_file_name)||is_link($scad_file_name)){
      process_scad_file($scad_file_name);
      $index_buffer[]='use <'.$scad_file_name.'>'."\n";
    }
  }

  file_put_contents($index_file_name,$index_buffer);
  chmod($index_file_name,0666);

}

///////////////////////////////////////////////////////////////////////////////////////////////////////////

function process_scad_file($scad_file_name){

  $scad_file_name_elements = pathinfo($scad_file_name);
  // print_r($scad_file_name_elements);
  if($scad_file_name_elements['extension']!='scad')
    exit(print_usage());

  $scad_file_content = file($scad_file_name);

  $scad_name = str_replace('.','_',str_replace('.scad','',$scad_file_name));
  $stl_header_file_name = str_replace('.scad','.stl.h',$scad_file_name);
  
  $cwd = getcwd();
  $parent_pid = getmypid();
  $datestamp = date("Y-m-d");

  // EXTRACT MODULE NAMES  ///////////////////////////////////////////////////

  if(!eregi('do_not_extract', $scad_file_content[0]))
    foreach($scad_file_content as $l){
      if(eregi('^module[ ]+([a-zA-Z0-9_]+)',$l,$matches)){
        $module_name = $matches[1];
        if(eregi('_dxf',$module_name)){
          if(!in_array($module_name,$module_names_dxf))
            $module_names_dxf[] = $module_name;
        }
        else if( !eregi('no_stl',$l) 
            && !in_array($module_name,$module_names_stl) )
          $module_names_stl[] = $module_name;
      }
      if(eregi('^[ \t]+module[ ]+([a-zA-Z0-9_]+)',$l,$matches)){
        $submodule_name = $matches[1];
        if(eregi('dxf',$l))
          $modules_submodules[$module_name][$submodule_name]['dxf'] = 1;
        if(eregi('stl',$l))
          $modules_submodules[$module_name][$submodule_name]['stl'] = 1;
      }
    }

  // MAKE STL DIRECTORY ///////////////////////////////////////////////////

  if(sizeof($module_names_stl)||sizeof($modules_submodules)){

    $stl_dir = $cwd . '/'.$scad_file_name_elements['filename'];
    $stl_dir_rel = $scad_file_name_elements['filename'];
  
    if(!is_dir($stl_dir))
      mkdir($stl_dir);
    if(is_dir($stl_dir))
      chmod($stl_dir,0777);
  
  }

  // MAKE DXF DIRECTORY ///////////////////////////////////////////////////

  if(sizeof($module_names_dxf)||sizeof($modules_submodules)){
    $dxf_dir = $cwd . '/' . $scad_file_name_elements['filename'];
    if(!is_dir($dxf_dir))
      mkdir($dxf_dir);
    if(is_dir($dxf_dir))
      chmod($dxf_dir,0777);
  }

  // SAVE HEADER FILE /////////////////////////////////////////////////////


  if(sizeof($module_names_stl)||sizeof($modules_submodules)){

    $buffer = '/*'."\n";
    foreach($module_names_stl as $module_name){
      $buffer .= '  '.$scad_name.'_stl("'.$module_name.'");'."\n";
    }
    $buffer .= '*/'."\n\n";

    $buffer .= 'module '.$scad_name.'_stl (select=""){'."\n\n";
      foreach($module_names_stl as $module_name){
        $buffer .= '  if(select=="'.$module_name.'") ';
        $buffer .= '  '.$module_name."();\n";
      }
      $buffer .= "\n";
      foreach($module_names_stl as $module_name){
        $buffer .= '  module '.$module_name."(){\n";
        $buffer .= '    import("'.$stl_dir_rel.'/'.$module_name.'.stl", convexity=3);'."\n";
        $buffer .= "  }\n";
      }
    $buffer .= "\n}\n";

    file_put_contents($stl_header_file_name,$buffer);
    chmod($stl_header_file_name,0666);
  
  }

  // EXPORT INDIVIDUAL STL FILES  ////////////////////////////////////////

  foreach($module_names_stl as $module_name){

    $pid = pcntl_fork();

    if($pid==-1) die('ERROR: could not fork');
    else if($pid){}
    else{

      chdir($stl_dir);
      $stl_file_name = $module_name.'.stl';

      if(!is_file($stl_file_name)){

        echo $stl_dir_rel.' / '.$stl_file_name."\n";
        
        $tmp_file_name = $module_name.'.tmp.scad';
        $tmp_file_content = "use <../".$scad_file_name.">\n";      
        $tmp_file_content .= $module_name."();\n";
        file_put_contents($tmp_file_name,$tmp_file_content);

        $cmd = "openscad -q -o $stl_file_name $tmp_file_name";
        //echo $cmd."\n";
        exec($cmd);
        if(is_file($stl_file_name))
          chmod($stl_file_name,0666);

        unlink($tmp_file_name);

      }

      exit;

    }
  }

  // EXPORT INDIVIDUAL STL FILES FROM SUBMODULES ////////////////////////////////////////

  foreach($modules_submodules as $root_module_name=>$submodules)
    foreach($submodules as $submodule_name=>$flags)
      if($flags['stl']){

        $module_name = $root_module_name.'.'.$submodule_name;

        $pid = pcntl_fork();

        if($pid==-1) die('ERROR: could not fork');
        else if($pid){}
        else{

          chdir($stl_dir);
          $stl_file_name = $module_name.'.stl';

          if(!is_file($stl_file_name)){

            echo 'stl: '.$module_name."\n";
            
            $tmp_file_name = $module_name.'.tmp.scad';
            $tmp_file_content = "use <../".$scad_file_name.">\n";      
            $tmp_file_content .= $root_module_name.'(select="'.$submodule_name.'");'."\n";
            file_put_contents($tmp_file_name,$tmp_file_content);

            $cmd = "openscad -q -o $stl_file_name $tmp_file_name";
            //echo $cmd."\n";
            exec($cmd);
            if(is_file($stl_file_name))
              chmod($stl_file_name,0666);

            unlink($tmp_file_name);

          }

          exit;

        }
      }


  // EXPORT INDIVIDUAL DXF FILES  ////////////////////////////////////////

  foreach($module_names_dxf as $module_name){

    $pid = pcntl_fork();

    if($pid==-1){
      die('ERROR: could not fork');
    }
    else if($pid){
      //pcntl_wait($status);
    }
    else{
      
      chdir($dxf_dir);
      $dxf_file_name = $module_name.'.dxf';

      if(!is_file($dxf_file_name)){

        echo 'dxf: '.$module_name."\n";
        
        $tmp_file_name = $module_name.'.tmp.scad';
        $tmp_file_content = "use <../".$scad_file_name.">\n";      
        $tmp_file_content .= 'projection()'.$module_name."();\n";
        file_put_contents($tmp_file_name,$tmp_file_content);

        $cmd = "openscad -q -o $dxf_file_name $tmp_file_name";
        //echo $cmd."\n";
        exec($cmd);
        if(is_file($dxf_file_name))
          chmod($stl_file_name,0666);

        unlink($tmp_file_name);

      }

      break;

    }
  }

  // EXPORT INDIVIDUAL DXF FILES FROM SUBMODULES ////////////////////////////////////////

  foreach($modules_submodules as $root_module_name=>$submodules)
    foreach($submodules as $submodule_name=>$flags)
      if($flags['dxf']){

        $module_name = $root_module_name.'.'.$submodule_name;

        $pid = pcntl_fork();

        if($pid==-1){
          die('ERROR: could not fork');
        }
        else if($pid){
          //pcntl_wait($status);
        }
        else{
          
          chdir($dxf_dir);
          $dxf_file_name = $module_name.'.dxf';

          if(!is_file($dxf_file_name)){

            echo 'dxf: '.$module_name."\n";
            
            $tmp_file_name = $module_name.'.tmp.scad';
            $tmp_file_content = "use <../".$scad_file_name.">\n";      
            $tmp_file_content .= 'projection()'.$root_module_name.'(select="'.$submodule_name.'");'."\n";
            file_put_contents($tmp_file_name,$tmp_file_content);

            $cmd = "openscad -q -o $dxf_file_name $tmp_file_name";
            //echo $cmd."\n";
            exec($cmd);
            if(is_file($dxf_file_name))
              chmod($stl_file_name,0666);

            unlink($tmp_file_name);

          }

          break;

        }
      }

}

///////////////////////////////////////////////////////////////////////////////////////////////////////////

function print_usage(){
  echo "USAGE: lscad \n";
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////

function deref_path($file_name){
  if(is_link($file_name))
    return(deref_path(readlink($file_name)));
  else
    return($file_name);
}

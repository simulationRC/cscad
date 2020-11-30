#!/usr/bin/php
<?php

//print_r($_SERVER);exit;

$module_names_stl = array();
$module_names_db = array();  // only nominal names
$module_names_dxf = array();

$module_name = '';
$module_name_nominal = '';

///////////////////////////////////////////////////////////////////////////////////////////////////////////

$cwd = getcwd();

if(sizeof($argv)==1){
  foreach(glob('*.scad') as $scad_file_name){
    $scad_file_name_elements = pathinfo($scad_file_name);
    $cwd_elements = explode('/',$cwd);
    // print_r($scad_file_name_elements);
    // print_r($cwd_elements);
    //echo $scad_file_name;
    if(is_file($scad_file_name) && 
      '_'.$cwd_elements[sizeof($cwd_elements)-1]==$scad_file_name_elements['filename'])
      process_scad_file($scad_file_name);
  }
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
      else if( !eregi('_exploded$',$module_name) &&
          !eregi('no_db',$l)){
        if(eregi('^CAM_',$module_name))
          $module_name_nominal = str_replace('CAM_','',$module_name);
        if(!in_array($module_name_nominal,$module_names_db))
          $module_names_db[] = $module_name_nominal;
      }
    }
    if(eregi('^[ \t]+module[ ]+([a-zA-Z0-9_]+)',$l,$matches)){
      $submodule_name = $matches[1];
      if(eregi('dxf',$l))
        $modules_submodules[$module_name][$submodule_name]['dxf'] = 1;
      if(eregi('stl',$l))
        $modules_submodules[$module_name][$submodule_name]['stl'] = 1;
    }
  }

  //print_r($module_names_stl);
  //print_r($module_names_db);

  // SAVE PARTS TO DB  ////////////////////////////////////////////////////
  /*
  $script_path = deref_path($_SERVER['PHP_SELF']);
  $script_dir = dirname($script_path);
  //require_once($script_dir.'/inc/db.php');
  //mysql_connect('localhost','root','');
  
  $cwd_array = explode('/',getcwd());
  if( sizeof($cwd_array)>=3 &&
      $cwd_array[sizeof($cwd_array)-2]=='model' ){
    $package_name = $cwd_array[sizeof($cwd_array)-1];
    $subject_name = $cwd_array[sizeof($cwd_array)-3];
  }

  if(isset($package_name)&&isset($subject_name)&&isset($part_name))
    foreach($module_names_db as $module_name){
      $row=array(
        'subject'=>$subject_name,
        'package'=>$package_name,
        'part_name'=>$module_name);
      if($row['package']==$row['part_name'])
        unset($row['part_name']);
      //print_r($row);
      minsert('operations.parts',$row,true);
      echo 
        'db: '.$row['subject'].
        ' / '.$row['package'].
        (strlen($row['part_name'])?' / '.$module_name:'').
        "\n";
    }
  */
  // MAKE STL DIRECTORY ///////////////////////////////////////////////////

  //$stl_dir = $cwd . '/' . str_replace('.scad','.stl',$scad_file_name);
  //$stl_dir_rel = str_replace('.scad','.stl',$scad_file_name);
  
  $stl_dir = $cwd . '/_stl_';
  $stl_dir_rel = '_stl_';
 
  if(!is_dir($stl_dir))
    mkdir($stl_dir);
  if(is_dir($stl_dir))
    chmod($stl_dir,0777);

  // MAKE DXF DIRECTORY ///////////////////////////////////////////////////

  //$dxf_dir = $cwd . '/' . str_replace('.scad','.dxf',$scad_file_name);
  $dxf_dir = $cwd . '/_dxf_';

  if(!is_dir($dxf_dir))
    mkdir($dxf_dir);
  if(is_dir($dxf_dir))
    chmod($dxf_dir,0777);

  // SAVE HEADER FILE /////////////////////////////////////////////////////

  $buffer = '/*'."\n\n";
  foreach($module_names_stl as $module_name){
    $buffer .= '  '.$scad_name.'_stl("'.$module_name.'");'."\n";
  }
  $buffer .= "\n".'*/'."\n\n";

  $buffer .= 'module '.$scad_name.'_stl (select="",t,c,scafold=false){'."\n\n";
    foreach($module_names_stl as $module_name){
      $buffer .= '  if(select=="'.$module_name.'") ';
      $buffer .= ' '.$module_name."()children();\n";
    }
    $buffer .= "\n";
    foreach($module_names_stl as $module_name){
//      $buffer .= ''."\n";
      $buffer .= '  module '.$module_name."(){\n";
      $buffer .= '    echo(parent_module(0));'."\n";
      $buffer .= '    lt_ = dict('.ereg_replace('^_','',$scad_name).'_transforms,parent_module(2),parent_module(0));'."\n";
      $buffer .= '    lt = lt_?lt_:translation([0,0,0]);'."\n";
      $buffer .= '    echo("lt =          ",lt);'."\n";
      $buffer .= '    $tt = $tt ? $tt * lt : translation([0,0,0]);'."\n";
      $buffer .= '    echo("$tt =         ",$tt);'."\n";
      $buffer .= '    label_ = dict('.ereg_replace('^_','',$scad_name).'_labels,parent_module(0));'."\n";
      $buffer .= '    label = label_?label_:[[0,0,0],[0,0,0],[0,0,-30]];'."\n";
      $buffer .= '    label_text = label[3]?label[3]:parent_module(0);'."\n";
      $buffer .= '    multmatrix(lt){'."\n";
      $buffer .= '      if(scafold==false)';
      $buffer .= '        color('."\n";
      $buffer .= '          c?c[0]:dict('.ereg_replace('^_','',$scad_name).'_colors,parent_module(0))[0],'."\n";
      $buffer .= '          c?c[1]:dict('.ereg_replace('^_','',$scad_name).'_colors,parent_module(0))[1])'."\n";
      $buffer .= '          import("'.$stl_dir_rel.'/'.$module_name.'.stl", convexity=3);'."\n";
      $buffer .= '      children();'."\n";
      $buffer .= '    }'."\n";
      
      $buffer .= '    if(scafold==false&&show_label==true){'."\n";
      $buffer .= '      if(label=="none"){}'."\n";
      $buffer .= '      else if(label)'."\n";
      $buffer .= '        color("black")multmatrix(lt)multmatrix(invert_rt($tt))translate([$tt[0][3],$tt[1][3],$tt[2][3]])rotate($vpr)rotate([-90,0,0]){'."\n";
      $buffer .= '            translate(label[2])'."\n";
      $buffer .= '              rotate([0,label[2][2]>0?-60:60,0])'."\n";
      $buffer .= '                translate([1,0,-1])'."\n";
      $buffer .= '                  rotate([90,0,0])'."\n";
      $buffer .= '                    linear_extrude(0.1)'."\n";
      $buffer .= '                      text(label_text,size=1.5);'."\n";
      $buffer .= '            hull(){translate(label[0])sphere(r=0.05);translate(label[1])sphere(r=0.05);}'."\n";
      $buffer .= '            hull(){translate(label[1])sphere(r=0.05);translate(label[2])sphere(r=0.05);}'."\n";
      $buffer .= '            translate(label[2])sphere(r=0.3);'."\n";
      $buffer .= '        }'."\n";
      $buffer .= '    }';
      $buffer .= "  }\n";


    }
  $buffer .= "\n}\n";



  file_put_contents($stl_header_file_name,$buffer);
  chmod($stl_header_file_name,0666);

  // EXPORT INDIVIDUAL STL FILES  ////////////////////////////////////////

  foreach($module_names_stl as $module_name){

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
  echo "USAGE: cscad \n";
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////

function deref_path($file_name){
  if(is_link($file_name))
    return(deref_path(readlink($file_name)));
  else
    return($file_name);
}

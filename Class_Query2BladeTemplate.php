<?php
include("inflector.php");

//$con = mysqli_connect('127.0.0.1','root','root','Laravel5_P1', 8889);
$con = mysqli_connect('127.0.0.1', 'root', 'root', 'Laravel5s', 8889);



$q2b = new Query2Blade($con);
$q2b->pathToProjectFolder = "/Users/jack/Desktop/WebFiddle.test/Laravel5s/scratchpad";
//$q2b->pathToProjectFolder = "/Users/jack/Desktop/WebFiddle.test/Laravel5/laravel";
//$q2b->subFolder = "admin";


$q2b->SaveAll();


#note, might want to switch logic to
# Show Tables
# and
# SHow Columns from Tablename

#more TODO:
# fix the path on update forms to include the admin route
# rename $this->subFolder to $this->route
# create the /admin/ controllers


Class Query2Blade
{

    /* this is a utility class to generate a table from a query
    */

    public $connection; //connection object  ie $db = new mysqli('localhost', 'root', 'root', 'PREIFTA',8889);
    public $pathToProjectFolder; // ex /webroot/laravel   not /webroot/laravel/app
    public $subFolder = "admin";  //ex "admin" or ""
    public $rootNameSpace = "App"; // ex "App" or your application's namesapce
    public $masterTemplate = "layouts.bootstrapmaster"; //change this if you want to use a different master template
    public $showAdvice = true; //shows you the developer some info about using the generated views.
    private $tables; //this will hold the list of tables, we'll do that in the constructor so we only need to do it once
    private $sidebarListItems;   //this will hold the sidebar li's -inited in constructor
    private $controllercomments; //this will hold the route instructions to add to the comments of each controller -inited in constructor
    private $systemFields = array('id', 'created_at', 'updated_at', 'deleted_at' ); //these fields will be hidden from auto generated views

    function __construct($con) {
        $this->connection = $con;
        $this->tables = $this->GetTables();
        $this->BuildSideBarListItems();
        $this->BuildControllerComments();
    }

    public function SetConnection(&$con)
    {
        $this->connection = $con;
    }

    public function DisplayAll()
    {

        $tables = $this->tables;
        $inf = new Inflector();

        foreach ($tables as $table)
        {
            echo $this->RenderIndexView($table, $inf->singularize($table));
            echo $this->RenderDetailView($table, $inf->singularize($table));
            echo $this->RenderCreateView($table, $inf->singularize($table));
            echo $this->RenderEditView($table, $inf->singularize($table));
        }
    }

    public function SaveAll()
    {

        $this->ResetDirectories();
        $tables = $this->GetTables();
        $inf = new Inflector();

        $this->EnsureProjectFolders();
        $this->EnsureTemplateMasterFolder();
        $this->SaveMasterIndexControllerandView();

        foreach ($tables as $table)
        {
            $folder = $this->EnsureViewFolder($table);

            $this->SaveRESTController($table, $inf->singularize($table));
            $this->SaveIndexView($table, $inf->singularize($table));
            $this->SaveDetailView($table, $inf->singularize($table));
            $this->SaveCreateView($table, $inf->singularize($table));
            $this->SaveEditView($table, $inf->singularize($table));
        }
    }

    private function ResetDirectories()
    {
        //warning, should only be used for testing!

        // note here the subfolder is hardcoded -
        // why? because if no subfolder is specfied,
        // I don't want to delete my views and controllers directories
        $templateFolder = $this->pathToProjectFolder."/resources/views/admin/";
        $ControllerFolder = $this->pathToProjectFolder."/app/Http/Controllers/admin/";

        $this->delTree($templateFolder);
        $this->delTree($ControllerFolder);

    }

    private function delTree($dir) {
       $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
          (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
      }

    private function GetTables()
    {
        $result = mysqli_query($this->connection, "Show Tables");
        if (!$result) {
            echo "Sql error" . mysqli_error($this->connection) . "->" . mysqli_errno($this->connection);
            exit;
        }

        $tablelist = array();
        while ($val = $result->fetch_array()) {
            //echo $val[0] . "\n";
            array_push($tablelist, $val[0]);
        }

        return $tablelist;
    }

    private function GetFields($table)
    {
        $result = $this->GetResult($table);
        return $result->fetch_fields();
    }

    private function GetQuoteDelimitedFieldList($table)
      {
          //build the table elements with blade syntax
          $fields = [];

          foreach ($this->getFields($table) as $val) {
              $fields[] = "'".$val->name."'";
          }

          return join(",", $fields);
      }

    private function GetQuoteDelimitedSafeFieldList($table)
      {
          //build the table elements with blade syntax
          $fields = [];

          foreach ($this->getFields($table) as $val) {
              if (!in_array (trim(strtolower($val->name)), $this->systemFields)) {
                  $fields[] = "'" . $val->name . "'";
              }
          }

          return join(",", $fields);
      }


    private function BuildSideBarListItems()
    {
        $inf = new Inflector();
        $string = "<a href=/".$this->subFolder.">Tables:</a><br/>\n";
        $string .= "<ul>\n";

        foreach ($this->tables as $table)
        {
            $string .= $this->GetIndent(8)."<li><a href='/".$this->subFolder."/".$inf->singularize($table)."'>".$table."</a></li>\n";
        }
        $string .= "</ul>\n";
        $this->sidebarListItems = $string;
    }

    private function BuildControllerComments()
    {
        // Add some comments so that new users have a starting point on what to add to the routes.php file
        $inf = new Inflector();


        $string = " * --- begin route block --- \n\n";
        $string .= "  Route::group([ 'prefix' => '".$this->subFolder."', 'namespace' => '".UCFirst($this->subFolder)."'], function(){\n";
        $string .= "    Route::get('/', 'MasterIndexController@index');\n";

        foreach ($this->tables as $table)
        {
            $string.= "    Route::resource('".$inf->singularize($table)."', '". UCFirst($inf->singularize($table))."Controller');\n";
        }
        $string .= "  });\n\n";
        $string .= " * --- end route block   --- \n";

        $this->controllercomments = $string;
    }

    private function EnsureProjectFolders()
    {
        if (!is_dir ($this->pathToProjectFolder)){
            die ("your project folder is invalid:". $this->pathToProjectFolder);
        }

        $templateFolder = $this->pathToProjectFolder."/resources/views/". $this->subFolder;
        if (is_dir ($templateFolder))
        {
            //die ("  You already have a: \n  ". $this->subFolder ."\n  Please remove or rename it\n");
        }
        mkdir($templateFolder, 0777, false);

        $ControllerFolder = $this->pathToProjectFolder."/app/Http/Controllers/". UCFirst($this->subFolder);
        if (is_dir ($ControllerFolder))
        {
            die ("  You already have a: \n  ". $ControllerFolder ."\n  Please remove or rename it\n");
        }
        mkdir($ControllerFolder, 0777, false);
    }


    private function EnsureTemplateMasterFolder()
    {
        // this basically makes sure there is a ../resources/views/layouts folder, or a /resources/views/admin/layouts folder, and places the bootstrap master blade subFolder there.
        if (!is_dir ($this->pathToProjectFolder)){
            die ("your project folder is invalid:". $this->pathToProjectFolder);
        }

        $templateMasterDir = join(DIRECTORY_SEPARATOR, array_filter(array($this->pathToProjectFolder, "resources", "views", $this->subFolder, "layouts" )));
        if (is_dir ($templateMasterDir))
        {
            die ("  You already have a: \n  ". $templateMasterDir ."\n  Please remove or rename it\n");
        }
        mkdir($templateMasterDir, 0777, false);

        //$stub = file_get_contents(__DIR__ . '/stubs/BootstrapMaster.stub');
        $stub = file_get_contents(join(DIRECTORY_SEPARATOR, Array(__DIR__, "stubs", "BootstrapMaster.stub")));
        $path = join(DIRECTORY_SEPARATOR, array_filter(array($this->pathToProjectFolder, "resources","views", $this->subFolder, "layouts", "bootstrapmaster.blade.php")));

        $stub = str_replace('{{path}}', $this->subFolder, $stub);
        file_put_contents($path, $stub);
    }

    private function EnsureViewFolder($table)
    {
        // future- create a $table directory under $this->pathToProjectFolder/app/Http/Controllers/$subFolder

        // create $this->pathToProjectFolder/resources/views/$subFolder/$table
        $viewDir = join(DIRECTORY_SEPARATOR, array_filter(array($this->pathToProjectFolder, "resources", "views", $this->subFolder, $table)));
        if(!mkdir($viewDir, 0777, false))
        {
            die("Unable to create $viewDir \n");
        };
        return $viewDir;
    }

    private function GetResult($table)
    {
        $result = mysqli_query($this->connection, "Select * from $table limit 1");
                if (!$result) {
                    echo "Sql error" . mysqli_error($this->connection) . "->" . mysqli_errno($this->connection);
                    exit;
                }
        return $result;
    }



    private function GetMasterTemplate()
    {
       return join(".", array_filter(array($this->subFolder, $this->masterTemplate)));
    }

    private function GetUpdateFunction($table)
    {
        $update = "\n";
        $update .=  $this->GetIndent(8)." \${{model}}Model = \\App\\{{model}}::find(\$id);\n";

        foreach ($this->GetFields($table) as $field)
        {
            //comment out lines for system fields by default
            $comment =  (in_array (trim(strtolower($field->name)), $this->systemFields)) ? "//" : "";
            $update .= $this->GetIndent(8) . $comment. "\$". $field->name . " = \\Request::input('".$field->name ."');\n";
            $update .= $this->GetIndent(8) . $comment. "\${{model}}Model->".$field->name." = \$".$field->name.";\n\n";
        }

        $update .= $this->GetIndent(8)."\${{model}}Model->save();\n";

        $update .= $this->GetIndent(8)."return \\Redirect::route('{{modelpath}}.edit', array(\${{model}}Model->id))->with('message', 'Your {{model}} has been updated');\n";
        return $update;
    }

    private function SaveRESTController($table, $model)
       {
           $text = $this->RenderRESTController($table, $model);
           $path = join(DIRECTORY_SEPARATOR, array_filter(array($this->pathToProjectFolder, "app", "Http", "Controllers", $this->subFolder, ucfirst($model)."Controller.php")));
           echo "\n PATH:".$path. "\n";
           file_put_contents($path, $text);
       }

    private function SaveMasterIndexControllerandView()
           {
               //save the controller
               $text = $this->RenderMasterIndexController();
               $path = join(DIRECTORY_SEPARATOR, array_filter(array($this->pathToProjectFolder, "app", "Http", "Controllers", $this->subFolder, "MasterIndexController.php")));
               echo "\n PATH:".$path. "\n";
               file_put_contents($path, $text);

               //save the view
               $text = $this->RenderMasterIndexView();
               $path = join(DIRECTORY_SEPARATOR, array_filter(array($this->pathToProjectFolder, "resources", "views", $this->subFolder,  "MasterIndex.blade.php")));

               file_put_contents($path, $text);
           }

    private function SaveIndexView($table, $model)
    {
        $text = $this->RenderIndexView($table, $model);
        $path = join(DIRECTORY_SEPARATOR, array_filter(array($this->pathToProjectFolder, "resources", "views", $this->subFolder, $table, "index.blade.php")));

        file_put_contents($path, $text);
    }

    private function SaveDetailView($table, $model)
    {
        $text = $this->RenderDetailView($table, $model);
        $path = join(DIRECTORY_SEPARATOR, array_filter(array($this->pathToProjectFolder, "resources","views", $this->subFolder, $table, "show.blade.php")));

        file_put_contents($path, $text);
    }

    private function SaveCreateView($table, $model)
    {
        $text = $this->RenderCreateView($table, $model);
        $path = join(DIRECTORY_SEPARATOR, array_filter(array($this->pathToProjectFolder, "resources","views", $this->subFolder, $table, "create.blade.php")));

        file_put_contents($path, $text);
    }

    private function SaveEditView($table, $model)
    {
        $text = $this->RenderEditView($table, $model);
        $path = join(DIRECTORY_SEPARATOR, array_filter(array($this->pathToProjectFolder, "resources","views", $this->subFolder, $table, "edit.blade.php")));

        file_put_contents($path, $text);
    }

    private function RenderRESTController($table, $model)
      {
          $modelPath = join(".", array_filter(array( $this->subFolder,$model)));
          $viewPath = join(".", array_filter(array( $this->subFolder, $table)));
          $routePath = join(DIRECTORY_SEPARATOR, array_filter(array($this->subFolder, $model)));
          $fieldList = $this->GetQuoteDelimitedFieldList($table);

          //create the basic REST view responses
          $index = "return view('{{viewpath}}.index')->with('{{model}}s', \\".$this->rootNameSpace."\\{{model}}::paginate(10));";
          $create = "return view('{{viewpath}}.create');";
          $store = "\\".$this->rootNameSpace."\\{{model}}::create(\\Request::all());\n";
          $store .= $this->GetIndent(8)."return redirect('{{routepath}}')->with('message', 'Your record has been saved.');\n";
          $show = "return view('{{viewpath}}.show')->with('{{model}}', \\".$this->rootNameSpace."\\{{model}}::find(\$id));\n";
          $edit = "return view('{{viewpath}}.edit')->with('{{model}}', \\".$this->rootNameSpace."\\{{model}}::find(\$id));\n";
          $update = $this->GetUpdateFunction($table);

          $destroy = "//Sorry, you'll have to add the destroy code yourself, hopefully after you've configured authentication";
          $destroy .= "// \${{model}} = {{model}}::find(\$id);";
          $destroy .= "// \${{model}}->delete();";



          $stub = file_get_contents(__DIR__ . '/stubs/controller.stub');

          $stub = str_replace('{{namespace}}', $this->rootNameSpace.'\Http\Controllers\\'.UCfirst($this->subFolder), $stub);
          $stub = str_replace('{{rootNamespace}}', $this->rootNameSpace, $stub);
          $stub = str_replace('{{class}}',  UCfirst($model)."Controller", $stub);

          $stub = str_replace('{{index}}',   $index, $stub);
          $stub = str_replace('{{create}}',  $create, $stub);
          $stub = str_replace('{{store}}',   $store, $stub);
          $stub = str_replace('{{show}}',    $show, $stub);
          $stub = str_replace('{{edit}}',    $edit, $stub);
          $stub = str_replace('{{update}}',  $update, $stub);
          $stub = str_replace('{{destroy}}', $destroy, $stub);
          $stub = str_replace('{{controllerComments}}', $this->controllercomments, $stub);

          //these must come last
          $stub = str_replace('{{viewpath}}', $viewPath, $stub);
          $stub = str_replace('{{routepath}}', $routePath, $stub);
          $stub = str_replace('{{modelpath}}', $modelPath, $stub);
          $stub = str_replace('{{model}}',   $model, $stub);
          $stub = str_replace('{{table}}',   $table, $stub);



          return $stub;
      }

    private function RenderMasterIndexController()
    {
         //  $modelPath = join(".", array_filter(array( $this->subFolder,$model)));
         $viewPath = join(".", array_filter(array( $this->subFolder, $table)));
         //$routePath = join(DIRECTORY_SEPARATOR, array_filter(array($this->subFolder, $model)));
         //
         $index = "return view('{{viewpath}}.Masterindex');";

         $stub = file_get_contents(__DIR__ . '/stubs/MasterIndexController.stub');
         $stub = str_replace('{{namespace}}', $this->rootNameSpace.'\Http\Controllers\\'.UCfirst($this->subFolder), $stub);
         $stub = str_replace('{{rootNamespace}}', $this->rootNameSpace, $stub);
         $stub = str_replace('{{class}}', 'MasterIndexController', $stub);
         $stub = str_replace('{{index}}',   $index, $stub);
         $stub = str_replace('{{controllerComments}}', $this->controllercomments, $stub);

         //these must come last
         $stub = str_replace('{{viewpath}}', $viewPath, $stub);
         //$stub = str_replace('{{routepath}}', $routePath, $stub);

         return $stub;
    }

    private function RenderIndexView($table, $model)
    {
        // purpose, Render the index view
        // connect to the database
        $result = $this->GetResult($table);

        //get the field names and write out the table header
        $header = ""; $indent = "";
        $fieldinfo = $result->fetch_fields();
        foreach ($fieldinfo as $val) {
            $header .= $indent . "<th>" . $val->name . "</th>\n";
            $indent = $this->GetIndent(8);
        }

        //now build the table elements with blade syntax
        $detail = ""; $indent = "";
        foreach ($fieldinfo as $val) {
            $detail .= $indent ."<td>{{ \$record->" . $val->name . "}}</td>\n";
            $indent = $this->GetIndent(8);
        }

        $hrefPath = join("/", array_filter(array($this->subFolder, $model)));

        $stub = file_get_contents(__DIR__ . '/stubs/IndexView.stub');

        $stub = str_replace('{{table}}', $table, $stub);
        $stub = str_replace('{{model}}', $model, $stub);
        $stub = str_replace('{{header}}', $header, $stub);
        $stub = str_replace('{{detail}}', $detail, $stub);
        $stub = str_replace('{{hrefPath}}', $hrefPath, $stub);
        $stub = str_replace('{{mastertemplate}}', $this->GetMasterTemplate(), $stub);
        $stub = str_replace('{{sidebarListItems}}', $this->sidebarListItems, $stub);

        return $stub;
    }

    private function RenderMasterIndexView()
    {
        // purpose, Render the index view
        $stub = file_get_contents(__DIR__ . '/stubs/MasterIndexView.stub');
        $stub = str_replace('{{mastertemplate}}', $this->GetMasterTemplate(), $stub);
        $stub = str_replace('{{sidebarListItems}}', $this->sidebarListItems, $stub);
        $stub = str_replace('{{SubFolder}}', $this->subFolder, $stub);

        return $stub;
    }



    private function RenderDetailView($table, $model)
    {
        //connect to the database
        $result = $this->GetResult($table);

        //build the table elements with blade syntax
        $fields = ""; $indent = "";
        $fieldinfo = $result->fetch_fields();
        foreach ($fieldinfo as $val) {
            $fields .= $indent . "<tr><td>$val->name</td><td>{{ \$$model->" . $val->name . " }}</td></tr>\n";
            $indent = $this->GetIndent(4);
        }
        $hrefPath = join("/", array_filter(array($this->subFolder, $model)));

        $stub = file_get_contents(__DIR__ . '/stubs/DetailView.stub');
        $stub = str_replace('{{table}}', $table, $stub);
        $stub = str_replace('{{model}}', $model, $stub);
        $stub = str_replace('{{hrefPath}}', $hrefPath, $stub);
        $stub = str_replace('{{fields}}', $fields, $stub);
        $stub = str_replace('{{mastertemplate}}', $this->GetMasterTemplate(), $stub);
        $stub = str_replace('{{sidebarListItems}}', $this->sidebarListItems, $stub);

        return $stub;
    }

    private function RenderEditView($table, $model)
    {
       return $this->RenderCreateOrEditView('Edit', $table, $model);
    }

    private function RenderCreateView($table, $model)
    {
        return $this->RenderCreateOrEditView('Create', $table, $model);
    }

    public function RenderCreateOrEditView($view, $table, $model)
    {
        if ($view == 'Edit')
        {
          $stubfile = "EditView.stub";
          $action = "update";
        } else {
          $stubfile = "CreateView.stub";
          $action = "create";
        }

        //connect to the database
        $result = $this->GetResult($table);

        //build the table elements with blade syntax
        $fields = "\n";
        $fieldinfo = $result->fetch_fields();
        foreach ($fieldinfo as $val) {
             //comment out lines for system fields by default
            if (in_array (trim(strtolower($val->name)), $this->systemFields))
            {
                $commentStart = "<!-- ";  $commentEnd = " -->";
            } else {
                $commentStart = ""; $commentEnd = "";
            }

            $fields .= $commentStart. "    <div class=\"form-group\">".$commentEnd."\n";
            $fields .= $commentStart. "        {!! Form::label('".$val->name."', '".$val->name."') !!} ".$commentEnd."\n";
            $fields .= $commentStart. "        {!! Form::text('". $val->name . "',null , array('class'=>'form-control')) !!} ".$commentEnd."\n";
            $fields .= $commentStart. "    </div>".$commentEnd."\n";

        }

        $hrefPath = join("/", array_filter(array($this->subFolder, $model)));
        $modelPath = join(".", array_filter(array( $this->subFolder, $model)));
        $fieldList = $this->GetQuoteDelimitedSafeFieldList($table);

        $stub = file_get_contents(__DIR__ . '/stubs/' . $stubfile);
        $modeladvice = file_get_contents(__DIR__ . '/stubs/ModelAdvice.stub');

        if ($this->showAdvice === true) {
            $stub = str_replace('{{modeladvice}}', $modeladvice, $stub);
        } else {
            $stub = str_replace('{{modeladvice}}', "", $stub);
        }
        $stub = str_replace('{{table}}', $table, $stub);
        $stub = str_replace('{{model}}', $model, $stub);
        $stub = str_replace('{{formContent}}', $fields, $stub);
        $stub = str_replace('{{hrefPath}}', $hrefPath, $stub);
        $stub = str_replace('{{modelpath}}', $modelPath, $stub);
        $stub = str_replace('{{mastertemplate}}', $this->GetMasterTemplate(), $stub);
        $stub = str_replace('{{sidebarListItems}}', $this->sidebarListItems, $stub);
        $stub = str_replace('{{fieldlist}}', $fieldList, $stub);
        $stub = str_replace('{{action}}', $action, $stub);

        return $stub;

    }

    private function GetIndent($num)
    {
        return str_repeat(" ", $num);
    }

}


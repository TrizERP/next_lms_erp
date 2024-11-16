<?php

namespace App\Http\Controllers\custom_module;

use App\Http\Controllers\Controller;
use App\Models\CustomModuleTable;
use App\Models\CustomModuleTableColumn;
use App\Models\DynamicModel;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\standardModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Opcodes\LogViewer\Log;
use function App\Helpers\is_mobile;
use Illuminate\Validation\Rule;

class CustomModuleController extends Controller
{
    public function tables(Request $request)
    {
        $subInstituteId = $request->session()->get('sub_institute_id');
        $tables = CustomModuleTable::where('sub_institute_id', $subInstituteId)->get();
        $data = ['data' => $tables->map(function ($table) {
            $tableExists = DB::select("SHOW TABLES LIKE '{$table['table_name']}'");
            $table['is_exists'] = count($tableExists);
            return $table;
        })];
        $type = $request->input('type');
        return is_mobile($type, "custom_modules.tables.index", $data, "view");
    }

    public function tableCreate(Request $request, $id = 0)
    {
        $type = $request->input('type');
        if ($id) {
            $customModuleTable = CustomModuleTable::with('whereColumns')->find($id);
            return is_mobile($type, "custom_modules.tables.create-edit", compact('customModuleTable'), "view", 'compact');
            //return view('HRMS.hrms_job_title.create', compact('hrmsJobTitle'));
        }
        $customModuleTable['table_name'] = '';
        $customModuleTable['module_name'] = '';
        $customModuleTable['module_type'] = '';
        $customModuleTable['display_under'] = '';
        $customModuleTable['migration'] = '';
        $customModuleTable['seeder'] = '';
        $customModuleTable['model'] = '';
        $customModuleTable['controller'] = '';
        $customModuleTable['route'] = '';
        $customModuleTable['view'] = '';
        $customModuleTable['storage'] = '';
        $customModuleTable['validation'] = '';
        $customModuleTable['access_link'] = '';
        $customModuleTable['id'] = 0;
        return is_mobile($type, "custom_modules.tables.create-edit", compact('customModuleTable'), "view", 'compact');
    }

    public function tableStore(Request $request)
    {
        $subInstituteId = $request->session()->get('sub_institute_id');
        $type = $request->input('type');
        $request->validate([
            'module_name' => 'required',
            'module_type' => 'required',
            'display_under' => 'required',
            'table_name' => 'required|string|unique:custom_module_tables,table_name,' . $request->id,
        ]);

        if ($request->id > 0) {
            $customModuleTable = CustomModuleTable::find($request->id);
        } else {
            $customModuleTable = new CustomModuleTable();
        }
        $prefixTableName = $request->table_name;
        if (!Str::startsWith($request->table_name, "Z_")) {
            $prefixTableName = "Z_" . $request->table_name;
        }

        $customModuleTable->module_name = $request->module_name;
        $customModuleTable->module_type = $request->module_type;
        $customModuleTable->display_under = $request->display_under;
        $customModuleTable->migration = $request->migration;
        $customModuleTable->seeder = $request->seeder;
        $customModuleTable->model = $request->model;
        $customModuleTable->controller = $request->controller;
        $customModuleTable->route = $request->route;
        $customModuleTable->view = $request->view;
        $customModuleTable->storage = $request->storage;
        $customModuleTable->validation = $request->validation;
        $customModuleTable->access_link = $request->access_link;
        $customModuleTable->table_name = $prefixTableName;
        $customModuleTable->sub_institute_id = $subInstituteId;
        $customModuleTable->save();

        $tableColumn = CustomModuleTableColumn::where('table_id', $customModuleTable->id);

        if (isset($request->student)) {
            $tableColumnData = $tableColumn->where([
                ['column_name', 'first_name'],
                ['column_name', 'middle_name'],
                ['column_name', 'last_name'],
                ['column_name', 'enrollment_no'],
                ['column_name', 'mobile'],
                ['column_name', 'mother_mobile'],
                ['column_name', 'email'],
                ['column_name', 'academic_section'],
                ['column_name', 'Standard'],
                ['column_name', 'Division'],
                ['column_name', 'roll_no']
            ])->first();
            if (!$tableColumnData) {
                DB::table('custom_module_table_columns')->insert([
                    ["column_name" => 'first_name', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'middle_name', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'last_name', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'enrollment_no', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'mobile', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'mother_mobile', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'email', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'academic_section', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'integer', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'Standard', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'integer', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'Division', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'integer', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'roll_no', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                ]);
            }
        } else {
            $tableColumn->where([
                ['column_name', 'first_name'],
                ['column_name', 'middle_name'],
                ['column_name', 'last_name'],
                ['column_name', 'enrollment_no'],
                ['column_name', 'mobile'],
                ['column_name', 'mother_mobile'],
                ['column_name', 'email'],
                ['column_name', 'academic_section'],
                ['column_name', 'Standard'],
                ['column_name', 'Division'],
                ['column_name', 'roll_no']
            ])->delete();
        }

        if (isset($request->staff)) {
            $tableColumnData = $tableColumn->where([
                ['column_name', 'first_name'],
                ['column_name', 'middle_name'],
                ['column_name', 'last_name'],
                ['column_name', 'staff_mobile'],
                ['column_name', 'email']
            ])->first();
            if (!$tableColumnData) {
                DB::table('custom_module_table_columns')->insert([
                    ["column_name" => 'first_name', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'middle_name', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'last_name', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'staff_mobile', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                    ["column_name" => 'email', "table_id" => $customModuleTable->id, "auto_increment" => 0, "type" => 'varchar', "length" => 255, 'not_null' => 0, 'index' => null, 'default' => null],
                ]);
            }
        } else {
            $tableColumn->where([
                ['column_name', 'first_name'],
                ['column_name', 'middle_name'],
                ['column_name', 'last_name'],
                ['column_name', 'staff_mobile'],
                ['column_name', 'email']
            ])->delete();
        }

        if (isset($request->division)) {
            $tableColumn = $tableColumn->where('column_name', 'Division')->first();
            if (!$tableColumn) {
                $tableColumn = new CustomModuleTableColumn();
                $tableColumn->column_name = 'Division';
                $tableColumn->table_id = $customModuleTable->id;
                $tableColumn->auto_increment = 0;
                $tableColumn->type = 'integer';
                $tableColumn->length = "255";
                $tableColumn->not_null = 0;
                $tableColumn->index = null;
                $tableColumn->default = null;
                $tableColumn->save();
            }
        } else {
            $tableColumn->where('column_name', 'Division')->delete();
        }
        if (isset($request->standard)) {
            $tableColumn = $tableColumn->where('column_name', 'Standard')->first();
            if (!$tableColumn) {
                $tableColumn = new CustomModuleTableColumn();
                $tableColumn->column_name = 'Standard';
                $tableColumn->table_id = $customModuleTable->id;
                $tableColumn->auto_increment = 0;
                $tableColumn->type = 'integer';
                $tableColumn->length = "255";
                $tableColumn->not_null = 0;
                $tableColumn->index = null;
                $tableColumn->default = null;
                $tableColumn->save();
            }
        } else {
            $tableColumn->where('column_name', 'Standard')->delete();
        }


        return is_mobile($type, "custom-module.tables", null, "redirect");
    }

    public
    function tableDelete(Request $request, $id)
    {
        $type = $request->input('type');
        if ($id > 0) {
            $table = CustomModuleTable::find($id);
            if (!empty($table)) {
                DB::statement('DROP TABLE IF EXISTS ' . $table->table_name);
            }
            CustomModuleTable::where('id', $id)->delete();
        }
        return is_mobile($type, "custom-module.tables", null, "redirect");
    }

    public
    function tableColumnCreate(Request $request, $id, $colId = 0)
    {
        $data['column_name'] = '';
        $data['column_length'] = 0;
        $data['column_type'] = '';
        $data['column_not_null'] = 0;
        $data['column_index'] = '';
        $data['column_default'] = '';
        $data['column_auto_increment'] = 0;
        $data['field_type'] = 'text-field';
        $data['field_value'] = '';
        $data['column_id'] = 0;
        if ($colId) {
            $findColumnData = CustomModuleTableColumn::find($colId);
            $data['column_name'] = $findColumnData['column_name'];
            $data['column_length'] = $findColumnData['length'];
            $data['column_type'] = $findColumnData['type'];
            $data['column_not_null'] = $findColumnData['not_null'];
            $data['column_index'] = $findColumnData['index'];
            $data['column_default'] = $findColumnData['default'];
            $data['column_auto_increment'] = $findColumnData['auto_increment'];
            $data['field_type'] = $findColumnData['field_type'];
            $data['field_value'] = implode(',', json_decode($findColumnData['field_value']));
            $data['column_id'] = $colId;
        }
        $data['data'] = CustomModuleTable::with('columns')->whereId($id)->first();
        $type = $request->input('type');
        return is_mobile($type, "custom_modules.tables.columns.index", $data, "view");
    }

    public
    function tableColumnStore(Request $request, $id)
    {
        $type = $request->input('type');
        $request->validate([
            'column_name' => [
                'required',
                Rule::unique('custom_module_table_columns')->where(function ($query) use ($id) {
                    return $query->where('table_id', $id);
                })->ignore($request->col_id)
            ],
        ]);
        if ($request->col_id) {
            $tableColumn = CustomModuleTableColumn::find($request->col_id);
        } else {
            $tableColumn = new CustomModuleTableColumn();
        }

        $tableColumn->column_name = Str::snake($request->column_name);
        $tableColumn->table_id = $id;
        $tableColumn->auto_increment = $request->has('column_auto_increment') ? 1 : 0;
        $tableColumn->type = $request->column_type;
        $tableColumn->length = $request->column_length;
        $tableColumn->not_null = $request->has('column_not_null') ? 1 : 0;
        $tableColumn->index = $request->column_index;
        $tableColumn->default = $request->column_default;
        $tableColumn->field_type = $request->field_type;
        $tableColumn->field_value = json_encode(explode(',', $request->field_value));
        $tableColumn->save();
        return is_mobile($type, ["route" => "custom_module_table_column.create", "id" => $id], null, "redirect", '', 1);

    }

    public
    function tableColumnDelete(Request $request, $id, $colId)
    {
        $type = $request->input('type');
        if ($id > 0) {
            CustomModuleTableColumn::where('id', $colId)->delete();
        }
        return is_mobile($type, ["route" => "custom_module_table_column.create", "id" => $id], null, "redirect", '', 1);
    }

    public
    function createDBTable(Request $request, $id)
    {
        $type = $request->input('type');
        $getTableData = CustomModuleTable::with('columns')->whereId($id)->first();
        if ($getTableData) {
            if (!count($getTableData['columns'])) {
                return is_mobile($type, ["route" => "custom_module_table_column.create", "id" => $id], ['message' => 'Please add at least one column'], "redirect", '', 1);
            }
        }
        $tableName = $getTableData['table_name'];
        $tableExists = DB::select("SHOW TABLES LIKE '{$tableName}'");

        if (!empty($tableExists)) {
            // Get existing columns in the table
            $existingColumns = DB::select("SHOW COLUMNS FROM {$tableName}");
            $existingColumnNames = array_column($existingColumns, 'Field');

            // Exclude 'created_at' and 'updated_at' columns from being altered
            $excludedColumns = ['id', 'sub_institute_id', 'created_at', 'updated_at'];

            // Prepare the column definitions for adding new columns
            $newColumns = [];
            $columnsToDrop = array_diff($existingColumnNames, $excludedColumns);

            foreach ($getTableData['columns'] as $column) {
                $columnName = $column['column_name'];
                $columnType = $column['type'];
                $columnLength = !empty($column['length']) ? "({$column['length']})" : ($column['type'] == 'varchar' ? "(255)" : " ");
                $autoIncrement = $column['auto_increment'] == 1 ? "AUTO_INCREMENT" : "";
                $notNull = $column['not_null'] == 1 ? "NOT NULL" : "";
                $defaultValue = isset($column['default']) ? "DEFAULT '{$column['default']}'" : "";

                $columnDefinition = "{$columnName} {$columnType}{$columnLength} {$notNull} {$defaultValue} {$autoIncrement}";

                if (!in_array($columnName, $existingColumnNames) && !in_array($columnName, $excludedColumns)) {
                    $newColumns[] = "ADD COLUMN {$columnDefinition}";
                } else {
                    $key = array_search($columnName, $columnsToDrop);
                    if ($key !== false) {
                        unset($columnsToDrop[$key]);
                    }
                }
            }

            // Prepare SQL for adding new columns
            if (!empty($newColumns)) {
                $alterTableSql = "ALTER TABLE {$tableName} " . implode(", ", $newColumns) . ";";
                DB::statement($alterTableSql);
            }

            // Prepare SQL for dropping columns
            if (!empty($columnsToDrop)) {
                $dropColumns = array_map(function ($column) {
                    return "DROP COLUMN {$column}";
                }, $columnsToDrop);
                $alterTableSql = "ALTER TABLE {$tableName} " . implode(", ", $dropColumns) . ";";
                DB::statement($alterTableSql);
            }
            return is_mobile($type, ["route" => "custom_module_table_column.create", "id" => $id], ['message' => "Table '{$tableName}' has been updated successfully."], "redirect", ['message' => "Table '{$tableName}' has been updated successfully."], 1);

            return "Table '{$tableName}' has been updated successfully.";
        } else {
            // Prepare the column definitions for creating the table
            $prepareColumn = [];
            $primaryKey = '';

            foreach ($getTableData['columns'] as $column) {
                $columnDefinition = $column['column_name'] . " " . $column['type'];

                if (isset($column['length'])) {
                    $columnDefinition .= $column['length'] > 0 ? " ({$column['length']})" : ($column['type'] == 'varchar' ? "(255)" : " ");
                }

                if ($column['auto_increment'] == 1) {
                    $columnDefinition .= " AUTO_INCREMENT";
                    $primaryKey = "PRIMARY KEY ({$column['column_name']})";
                }

                if ($column['not_null'] == 1) {
                    $columnDefinition .= " NOT NULL";
                }

                if (isset($column['default'])) {
                    $columnDefinition .= " DEFAULT '{$column['default']}'";
                }

                $prepareColumn[] = $columnDefinition;
            }

            if ($primaryKey) {
                $prepareColumn[] = $primaryKey;
            }

            $columns = implode(",\n", $prepareColumn);

            $sql = "
        CREATE TABLE {$tableName} (
              id bigint NOT NULL AUTO_INCREMENT,
            " . $columns . ",
            sub_institute_id int NOT NULL DEFAULT '0',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=INNODB;
    ";

            DB::statement($sql);

            return is_mobile($type, ["route" => "custom_module_table_column.create", "id" => $id], ['message' => "Table '{$tableName}' has been created successfully."], "redirect", ['message' => "Table '{$tableName}' has been created successfully."], 1);
        }

// sub_institute_id int NOT NULL DEFAULT '0',
    }

    public
    function crudIndex(Request $request, $id)
    {
        $type = $request->input('type');
        $subInstituteId = $request->session()->get('sub_institute_id');
        $data['data'] = CustomModuleTable::with('columns')->where([['sub_institute_id', $subInstituteId], ['id', $id]])->first();
        $data['data']['view'] = DynamicModel::readRecords($data['data']['table_name']);
        $data['data']['division'] = divisionModel::where('sub_institute_id', $request->session()->get('sub_institute_id'))->get(['id', 'name']);
        $data['data']['standard'] = standardModel::where('sub_institute_id', $request->session()->get('sub_institute_id'))->get(['id', 'name']);
        $data['data']['academic_section'] = academic_sectionModel::where('sub_institute_id', $request->session()->get('sub_institute_id'))->get(['id', 'title', 'short_name', 'medium']);
        return is_mobile($type, "custom_modules.cruds.index", $data, "view");
    }

    public
    function crudCreate(Request $request, $id, $viewId = 0)
    {
        $data['data'] = CustomModuleTable::with('columns')->find($id);
        $prepareView = [];
        foreach ($data['data']['columns'] as $key => $column) {
            $prepareView[$column['column_name']] = '';
        }
        $prepareView['id'] = 0;
        $data['data']['view'] = $prepareView;
        $data['data']['division'] = divisionModel::where('sub_institute_id', $request->session()->get('sub_institute_id'))->get(['id', 'name']);
        $data['data']['standard'] = standardModel::where('sub_institute_id', $request->session()->get('sub_institute_id'))->get(['id', 'name']);
        $data['data']['academic_section'] = academic_sectionModel::where('sub_institute_id', $request->session()->get('sub_institute_id'))->get(['id', 'title', 'short_name', 'medium']);

        if (isset($data['data']['table_name'])) {
            $getRecords = DynamicModel::readSingleRecord($data['data']['table_name'], $viewId);
            if ($getRecords) $data['data']['view'] = $getRecords;
        }
        $type = $request->input('type');
        return is_mobile($type, "custom_modules.cruds.edit", $data, "view");
    }

    public
    function crudStore(Request $request, $id)
    {
        $getTable = CustomModuleTable::with('columns')->find($id);
        $fileKeys = collect($getTable['columns'])->where('field_type', 'File')->pluck('column_name')->toArray();
        $checkboxKeys = collect($getTable['columns'])->where('field_type', 'checkbox')->pluck('column_name')->toArray();
        $exceptKey = ['_token', 'view_id', 'submit'];

        $validationKeys = collect($getTable['columns'])->where('not_null',1)->pluck('column_name')->toArray();
        $prepareValidation = [];
        foreach ($validationKeys as $validationKey) {
            $prepareValidation[$validationKey] = 'required';
        }
        if ($id) {
           foreach ($fileKeys as $fileKey) {
               unset($prepareValidation[$fileKey]);
           }
        }

        foreach ($request->files as $key => $file) {
            if (Str::startsWith($key, 'new_')) {
                $exceptKey[] = $key;
            }
        }

        $request->validate($prepareValidation);
        $data = $request->except($exceptKey);
        $columns = collect($data)->keys()->toArray();

        $type = $request->input('type');
        if ($id) {
            if ($request->view_id) {
                foreach ($request->files as $key => $file) {
                    if (Str::startsWith($key, 'new_')) {
                        $imageName = time() . '.' . $file->getClientOriginalExtension();
                        $file->move(public_path('images'), $imageName);
                        $oldKey = Str::replaceFirst('new_', '', $key);
                        $data[$oldKey] = $imageName;

                    }
                }
            }

            foreach ($request->files as $key => $file) {
                if (in_array($key, $fileKeys)) {
                    $imageName = time() . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('images'), $imageName);
                    $data[$key] = $imageName;
                }
                /*if (Str::startsWith($key, 'image_')) {
                    $imageName = time() . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('images'), $imageName);
                    $data[$key] = $imageName;
                }*/
            }

            $requestData = $request->all();
            foreach (collect($requestData)->toArray() as $key => $requestValue) {
                if (in_array($key, $checkboxKeys)) {
                    $data[$key] = json_encode($requestValue);
                }
            }


            $data = array_filter($data, function ($key) {
                return strpos($key, 'new_') !== 0;
            }, ARRAY_FILTER_USE_KEY);



            if (!empty($getTable)) {
                $data['sub_institute_id'] = $request->session()->get('sub_institute_id');
                $dynamicModel = new DynamicModel([], $columns);
                if ($request->view_id) {
                    $dynamicModel->updateRecord($getTable['table_name'], $request->view_id, $data);
                } else {
                    $dynamicModel->createRecord($getTable['table_name'], $data);
                }
            }
        }
        return is_mobile($type, ["route" => "custom_module_crud.index", "id" => $id], null, "redirect", '', 1);

    }

    public
    function viewDelete(Request $request, $id)
    {
        $type = $request->input('type');
        if ($id > 0 && $request->table_name) {
            DynamicModel::deleteRecord($request->table_name, $id);
        }
        return is_mobile($type, ["route" => "custom_module_crud.index", "id" => $request->view_id], null, "redirect", '', 1);
    }
}

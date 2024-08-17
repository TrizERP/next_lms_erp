<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class DynamicModel extends Model
{
    use HasFactory;

    protected $table;
    protected $fillable = [];

    public function __construct(array $attributes = [], array $columns = [])
    {
        parent::__construct($attributes);
        $this->fillable = $columns;
    }

    /**
     * Initialize the model with the given table.
     *
     * @param string $table
     * @return $this
     */
    public function initialize(string $table)
    {
        $this->setTable("Z_".$table);
        $this->setFillableAttributes();

        return $this;
    }

    /**
     * Set the table name dynamically.
     *
     * @param string $table
     */
    public function setTable($table)
    {
        $this->table = $table;
        parent::setTable($table); // Set the table for the parent Model class
    }

    /**
     * Set the fillable attributes dynamically based on the table columns.
     */
    protected function setFillableAttributes()
    {
        if (Schema::hasTable($this->table)) {
            $this->fillable = Schema::getColumnListing($this->table);

            // Debugging information
            Log::info('Table ' . $this->table . ' fillable attributes: ' . implode(', ', $this->fillable));
        } else {
            throw new \Exception("Table {$this->table} does not exist.");
        }
    }

    /**
     * Create a new record in the dynamic table.
     *
     * @param string $table
     * @param array $data
     * @return Model
     * @throws \Exception
     */
    public static function createRecord(string $table, array $data)
    {
        DB::table("Z_".$table)->insert($data);
       /* $instance = (new static())->initialize($table);

        // Ensure all data keys are fillable
        $fillableAttributes = $instance->getFillable();
        $dataKeys = array_keys($data);

        $nonFillableKeys = array_diff($dataKeys, $fillableAttributes);
        if (!empty($nonFillableKeys)) {
            throw new \Exception('Non-fillable attributes detected: ' . implode(', ', $nonFillableKeys));
        }

        Log::info('Creating record with data: ' . json_encode($data));

        $createdRecord = $instance->create($data);

        Log::info('Record created: ' . json_encode($createdRecord));

        return $createdRecord;*/
    }

    /**
     * Retrieve records from the dynamic table.
     *
     * @param string $table
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function readRecords(string $table)
    {
        $instance = (new static())->initialize($table);
        return $instance->get();
    }

    public static function readSingleRecord(string $table, int $id)
    {
        $instance = (new static())->initialize($table);
        return $instance->find($id);
    }

    /**
     * Update a record in the dynamic table by ID.
     *
     * @param string $table
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function updateRecord(string $table, int $id, array $data)
    {
        DB::table($table)->where('id', $id)->update($data);
        /*$instance = (new static())->initialize($table);
        $record = $instance->findOrFail($id);
        return $record->update($data);*/
    }

    /**
     * Delete a record from the dynamic table by ID.
     *
     * @param string $table
     * @param int $id
     * @return bool|null
     */
    public static function deleteRecord(string $table, int $id)
    {
        $instance = (new static())->initialize($table);
        $record = $instance->findOrFail($id);
        return $record->delete();
    }
}

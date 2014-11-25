<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEntities extends Migration
{


    protected $foreign_keys = [];


    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $config = Config::get('entity');

        foreach($config as $identifier => $entity){

            $entity['attributes'] = $this->mergeAttributes($entity['attributes']);

            // Create base entity table
            Schema::create($entity['table'], function ($table) use ($entity){

                $table->engine = 'InnoDB';

                $table->increments('id');

                if($entity['timestamps']){

                    $table->timestamps();
                }

                // If we have attributes to add create them now
                if($entity['attributes']){

                    // Append scope columns
                    $this->addScope($table, $entity);

                    foreach($entity['attributes'] as $column => $attribute){

                        $parent = isset($attribute['parent']) ? $attribute['parent'] : null;

                        $this->addColumn(
                            $table,
                            $parent ? 'int-unsigned' : $attribute['type'],
                            $column,
                            $parent ? null : $attribute['default'],
                            $parent
                        );
                    }
                }
            });
        }

        // Now that tables are built, lets apply all foreign keys
        foreach($this->foreign_keys as $table => $fks){

            Schema::table($table, function ($table) use ($fks, $config){

                foreach($fks as $fk){

                    $table->foreign($fk['col'])
                        ->references($fk['ref_col'])
                        ->on($config[$fk['ref_table']]['table'])
                        ->onDelete('cascade');
                }
            });
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $config = Config::get('entity');

        foreach($config as $identifier => $entity){

            if($entity['attributes'] && $entity['type'] == Lavender::ENTITY_EAV){

                foreach(Lavender::$eav_types as $type){

                    Schema::drop($entity['table'] . '_attribute_' . $type);
                }

                Schema::drop($entity['table'] . '_attribute');
            }

            Schema::drop($entity['table']);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }


    /**
     * @param $table
     * @param $entity
     */
    protected function addScope(&$table, $entity)
    {
        if($entity['scope'] == Lavender::SCOPE_STORE){

            $this->addColumn($table, 'int-unsigned', 'store_id', 'store');
            $table->index('store_id');
        } elseif($entity['scope'] == Lavender::SCOPE_DEPARTMENT){

            $this->addColumn($table, 'int-unsigned', 'department_id', 'department');
            $table->index('department_id');

            $this->addColumn($table, 'int-unsigned', 'store_id', 'store');
            $table->index('store_id');
        }
    }


    /**
     * @param $table
     * @param $type
     * @param $column
     * @param null $default
     * @param null $parent
     */
    protected function addColumn(&$table, $type, $column, $default = null, $parent = null)
    {
        switch($type){
            case 'text':
                $table->longText($column);
                break;
            case 'int-unsigned':
                $table->integer($column)->unsigned()->nullable();
                break;
            case 'int':
                $table->integer($column)->default($default);
                break;
            case 'decimal':
                $table->decimal($column, 12, 4)->default($default);
                break;
            case 'date':
                $table->dateTime($column)->default($default);
                break;
            default:
                $table->string($column, 150)->default($default);
                break;
        }

        if($parent){

            $this->foreign_keys[$table->getTable()][] = [
                'col'       => $column,
                'ref_table' => $parent,
                'ref_col'   => 'id',
            ];
        }
    }


    /**
     * @param array $attributes
     * @return array
     */
    protected function mergeAttributes($attributes)
    {
        $merged = [];

        $defaults = Config::get('defaults');

        foreach($attributes as $attr => $attribute){

            $merged[$attr] = $this->merge(
                $defaults['attribute'],
                $attribute
            );
        }

        return $merged;
    }


    /**
     * Array merge recursive
     * @param array $arr1
     * @param array $arr2
     * @return array
     */
    protected function merge($arr1, $arr2)
    {
        if(!is_array($arr1) || !is_array($arr2)){
            return $arr2;
        }

        foreach($arr2 as $key => $val){

            $arr1[$key] = $this->merge(@$arr1[$key], $val);
        }

        return $arr1;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: DanielSimangunsong
 * Date: 1/16/2017
 * Time: 1:34 PM
 */

namespace Webarq\Lang;


use DB;
use Wa;
use Webarq\Info\TableInfo;
use Webarq\Model\NoModel;

class Wl
{
    /**
     * System language
     *
     * @var string
     */
    protected $system = 'en';

    /**
     * Default language
     *
     * @var string
     */
    protected $default = 'en';

    /**
     * @var array
     */
    protected $langCodeColumn = ['name' => 'lang_code', 'type' => 'char', 'length' => 2, 'notnull' => true];

    /**
     * @return string
     */
    public function getSystem()
    {
        return $this->system;
    }

    /**
     * @return string
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Set the language default
     *
     * @param string $code
     */
    public function setDefault($code)
    {
        if (!in_array($code, $this->getCodes())) {
            $code = $this->system;
        }

        $this->default = $code;
    }

    /**
     * @return array
     */
    public function getCodes()
    {
        return config('webarq.system.lang', ['en', 'id']);
    }

    /**
     * @param TableInfo $table
     * @param $id
     * @return array
     */
    public function getTranslation(TableInfo $table, $id)
    {
        $code = $this->getLangCodeColumn('name');
        $rows = [];
// Load model
        $model = NoModel::instance($this->translateTableName($table->getName()), $table->primaryColumn()->getName());
// Get row
        $get = $model->where($table->getReferenceKeyName(), $id)->get();
// Grouping by localization code
        if ($get->count()) {
            foreach ($get as $row) {
                $rows[$row->{$code}] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param null $key
     * @param null $default
     * @return array|mixed
     */
    public function getLangCodeColumn($key = null, $default = null)
    {
        return array_get($this->langCodeColumn, $key, $default);
    }

    /**
     * @param $table
     * @return string
     */
    public function translateTableName($table)
    {
        return $table . '_i18n';
    }

    /**
     * @param $table
     * @param $id
     * @param $pairs
     * @return int
     */
    public function updateTranslations($table, $id, $pairs)
    {
        $count = 0;

        foreach ($pairs as $lang => $data) {
//            Find if the translation exist
            $find = DB::table($this->translateTableName($table))
                    ->where(Wa::table($table)->getReferenceKeyName(), $id)
                    ->where($this->getLangCodeColumn('name'), $lang)
                    ->get();
//            Update if found
            if ($find->count()) {
                $count += DB::table($this->translateTableName($table))
                        ->where(Wa::table($table)->getReferenceKeyName(), $id)
                        ->where($this->getLangCodeColumn('name'), $lang)
                        ->update($data);
            } else {
//                Otherwise just insert a new record
                $this->storeTranslations($table, $id, [$lang => $data]);
            }
        }

        return $count;
    }

    /**
     * @param $table
     * @param $id
     * @param array $pairs
     * @return int
     */
    public function storeTranslations($table, $id, array $pairs)
    {
        $count = 0;

        foreach ($pairs as $lang => $data) {
            $count += DB::table($this->translateTableName($table))->insert($data + [
                            $this->getLangCodeColumn('name') => $lang,
                            Wa::table($table)->getReferenceKeyName() => $id,
                            'create_on' => date('Y-m-d H:i:s')
                    ]);
        }

        return $count;
    }

    /**
     * @param $table
     * @param $id
     * @param array $columns
     * @param null $code
     * @return mixed
     */
    public function getTranslationsRow($table, $id, array $columns = null, $code = null)
    {
        $builder = DB::table($this->translateTableName($table))
                ->where(Wa::table($table)->getReferenceKeyName(), $id);

        if (isset($code)) {
            $builder->where($this->getLangCodeColumn('name'), $code);
        } else {
            $columns[] = $this->getLangCodeColumn('name');
        }

        return $builder->get($columns);
    }

    /**
     * @param $column
     * @return array
     */
    public function checkColumnAlias($column)
    {
        return explode(' as ', $column, 2) + [1 => $column];
    }
}
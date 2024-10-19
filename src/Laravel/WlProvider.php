<?php
/**
 * Created by PhpStorm.
 * User: DanielSimangunsong
 * Date: 1/24/2017
 * Time: 10:31 AM
 */

namespace Webarq\Lang\Laravel;


use DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\ServiceProvider;
use Wa;
use Webarq\Lang\Wl;

class WlProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('wl', function () {
            return new Wl();
        });

        Builder::macro('tables', function () {
            $tables = [];
            $aliases = Wa::tableAliases($this->from, true);
            $tables[$aliases[1]] = $aliases[0];

            if (null !== ($j = $this->joins) && is_array($j)) {
                foreach ($j as $i) {
                    $aliases = Wa::tableAliases($i->table, true);
                    $tables[$aliases[1]] = $aliases[0];
                }
            }

            return $tables;
        });

        Builder::macro('makeSelectTranslate', function ($lang, ... $columns) {
            if ([] !== $columns) {
                $tables = $this->tables();
                $check = is_bool(last($columns)) ? array_pop($columns) : false;
                $joins = [];
                $tAlias = null;

                foreach ($tables as $tAlias => $tName) break;

                foreach ($columns as $column) {
                    if (!str_contains($column, '.')) {
                        $alias = $tAlias;
                    } else {
                        list($alias, $column) = explode('.', $column, 2);
                    }

                    if (null !== ($table = array_get($tables, $alias))
                            && null !== ($info = Wa::table($table))
                            && $info->isMultilingual()
                    ) {
                        $x = $alias . 't';
                        $y = $alias . '.' . $info->primaryColumn()->getName();
                        $z = $x . '.' . $info->getReferenceKeyName();

                        if (!isset($joins[$alias])) {
                            $this->leftJoin(\Wl::translateTableName($info->getName()) . ' as ' . $x, function ($join)
                            use ($x, $y, $z, $lang) {
                                $join->on($y, $z)
                                        ->where($x . '.' . \Wl::getLangCodeColumn('name'), $lang);
                            });

                            $joins[$alias] = true;
                        }

// Get both of original column and translation column
                        if (ends_with($column, '!')) {
                            list($column, $columnAlias) = \Wl::checkColumnAlias(substr($column, 0, -1));
                            $this->addSelect(
                                    $x . '.' . $column . ' as ' . $columnAlias . '_lang',
                                    $alias . '.' . $column . ' as ' . $columnAlias
                            );
                        } elseif (true === $check) {
                            list($column, $columnAlias) = \Wl::checkColumnAlias($column);
// Check if translation column is null
                            $this->addSelect(\DB::raw(
                                    'CASE WHEN ' . $x . '.' . $column . ' IS NULL OR ' . $x . '.' . $column . ' = \'\''
                                    . ' THEN ' . $alias . '.' . $column
                                    . ' ELSE ' . $x . '.' . $column
                                    . ' END as ' . $columnAlias
                            ));
                        } else {
                            list($column, $columnAlias) = \Wl::checkColumnAlias($column);
// Just get the translation column
                            $this->addSelect($x . '.' . $column . ' as ' . $columnAlias);
                        }
                    } else {
                        list($column, $columnAlias) = \Wl::checkColumnAlias($column);
                        $this->addSelect($alias . '.' . $column . ' as ' . $columnAlias);
                    }
                }
            }
        });

        Builder::macro('caseWhenRaw', function ($first, $second, $operator, $value) {
            $bindings = [$value, $value];
            switch ($operator) {
                case 'between':
                case 'not between':
                    $hint = $operator . ' ? AND ?';
                    break;
                case 'in':
                case 'not in':
                    $hint = $operator . ' (' . trim(str_repeat('?, ', count($value)), ', ') . ')';
                    break;
                default :
                    $hint = $operator . ' ?';
                    break;
            }
            $raw = 'CASE WHEN ' . $first . ' IS NULL OR ' . $first . ' = \'\'';
            $raw .= ' THEN ' . $second . ' ' . $hint;
            $raw .= ' ELSE ' . $first . ' ' . $hint;
            $raw .= ' END';

            return [DB::raw($raw), $bindings];
        });

        Builder::macro('makeWhereTranslate', function ($column, $operator = '=', $value = null, $boolean = 'and',
                                                       $check = false) {
            if (!str_contains($column, '.')) {
                $alias = Wa::tableAliases($this->from);
            } else {
                list($alias, $column) = explode('.', $column);
            }

            $x = $alias . 't' . '.' . $column;

            if (true === $check) {
                $y = $alias . '.' . $column;

                list ($raw, $value) = $this->caseWhenRaw($x, $y, $operator, $value);

                $this->whereRaw($raw, $value, $boolean);
            } else {
                switch ($operator) {
                    case 'between':
                        $this->whereBetween($x, $value, $boolean);
                        break;
                    case 'not between':
                        $this->whereBetween($x, $value, $boolean, true);
                        break;
                    case 'in':
                        $this->whereIn($x, $value, $boolean);
                        break;
                    case 'not in':
                        $this->whereIn($x, $value, $boolean, true);
                        break;
                    default:
                        $this->where($x, $operator, $value, $boolean);
                        break;
                }
            }
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('wl');
    }
}
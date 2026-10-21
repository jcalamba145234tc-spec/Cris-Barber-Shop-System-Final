<?php

namespace App\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;

class StrictMySqlGrammar extends MySqlGrammar
{
    public function compileExists(Builder $query)
    {
        // EXISTS needs a row, not every column in a group. Laravel's implicit
        // SELECT * is rejected by Oracle MySQL's ONLY_FULL_GROUP_BY even though
        // the projected values are unused. Keep explicit projections, aggregates
        // and unions unchanged, and do not mutate the caller's reusable builder.
        if ($query->groups && $query->columns === null && ! $query->aggregate && ! $query->unions) {
            $query = clone $query;
            $query->selectRaw('1');
        }

        return parent::compileExists($query);
    }
}

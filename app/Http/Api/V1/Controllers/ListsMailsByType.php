<?php

namespace App\Http\Api\V1\Controllers;

use CatLab\Charon\Interfaces\ResourceTransformer;
use Illuminate\Http\Request;

/**
 * For the mail resources that are a fixed list by type and not a query:
 * the `type` filter and the sort on type of their index.
 *
 * @package App\Http\Api\V1\Controllers
 */
trait ListsMailsByType
{
    /**
     * @param Request $request
     * @param \App\Models\EmailTemplate[]|\App\Models\EventEmail[] $mails
     * @return array
     */
    protected function filterAndSortByType(Request $request, array $mails): array
    {
        $type = $request->query('type');
        if (is_string($type) && $type !== '') {
            $mails = array_values(array_filter($mails, function ($mail) use ($type) {
                return $mail->type === $type;
            }));
        }

        // Charon's sort is a list of fields; this list only knows one.
        $sort = $request->query(ResourceTransformer::SORT_PARAMETER);
        if (is_array($sort)) {
            $sort = reset($sort);
        }
        if (is_string($sort)) {
            $sort = explode(',', $sort)[0];
        }
        if ($sort === 'type' || $sort === '!type') {
            usort($mails, function ($a, $b) use ($sort) {
                return $sort === 'type' ? strcmp($a->type, $b->type) : strcmp($b->type, $a->type);
            });
        }

        return $mails;
    }
}

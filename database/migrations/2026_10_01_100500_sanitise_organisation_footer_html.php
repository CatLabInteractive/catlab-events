<?php

use App\Cms\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * organisations.footer_html is printed unescaped in the footer. From now on
 * it is sanitised whenever it is written (Organisation::booted()); this
 * one-off pass cleans the values stored before that, with the same rules as
 * CMS rich text. Not reversible: the removed markup was unsafe by definition.
 */
class SanitiseOrganisationFooterHtml extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $sanitizer = app(HtmlSanitizer::class);

        $rows = DB::table('organisations')
            ->whereNotNull('footer_html')
            ->select([ 'id', 'footer_html' ])
            ->get();

        foreach ($rows as $row) {
            $clean = $sanitizer->sanitize($row->footer_html);
            $clean = $clean === '' ? null : $clean;

            if ($clean !== $row->footer_html) {
                DB::table('organisations')
                    ->where('id', '=', $row->id)
                    ->update([ 'footer_html' => $clean ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Nothing to restore.
    }
}

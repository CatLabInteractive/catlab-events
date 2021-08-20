<?php
/**
 * CatLab Events - Event ticketing system
 * Copyright (C) 2017 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Http\Controllers\Admin;

use App\Http\Api\V1\ResourceDefinitions\Events\EventDateResourceDefinition;
use App\Http\Api\V1\ResourceDefinitions\Events\TicketCategoryResourceDefinition;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Order;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use CatLab\Charon\Interfaces\Context as ContextContract;
use CatLab\CharonFrontend\Models\Table\ResourceAction;
use CatLab\Laravel\Table\Table;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PDF;

/**
 * Class EventController
 * @package App\Http\Controllers\Admin
 */
class EventController extends Controller
{
    use FrontCrudController;

    /**
     * @param $path
     * @param $controller
     * @param string $modelId
     */
    public static function routes($path, $controller, $modelId = 'id')
    {
        self::traitRoutes($path, $controller, $modelId);

        \Route::get($path . '/{' . $modelId . '}/fetchScore', $controller . '@fetchScore');
    }

    /**
     * EventController constructor.
     */
    public function __construct()
    {
        $this->setLayout('layouts.admin');
        $this->setChildController(TicketCategoryResourceDefinition::class, TicketCategoryController::class);
        $this->setChildController(EventDateResourceDefinition::class, EventDateController::class);
    }

    /**
     * @return \App\Http\Api\V1\Controllers\Events\EventController
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    public function createApiController()
    {
        return new \App\Http\Api\V1\Controllers\Events\EventController();
    }

    /**
     * @param Request $request
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param ContextContract $context
     * @return Table
     */
    public function getTableForResourceCollection (
        Request $request,
        ResourceCollection $collection,
        ResourceDefinition $resourceDefinition,
        ContextContract $context
    ): Table {
        $table = $this->traitGetTableForResourceCollection($request, $collection, $resourceDefinition, $context);

        $methods = [
            'exportMembers' => 'Email csv',
            'exportSales' => 'Teams',
            'exportSalesTimeline' => 'Historiek registraties',
            'exportClearing' => 'Clearing'
        ];

        foreach ($methods as $method => $description) {
            $table->modelAction(
                (new ResourceAction('Admin\EventController@' . $method, $description))
                    ->setRouteParameters($this->getShowRouteParameters($request))
                    ->setQueryParameters($this->getShowQueryParameters($request))
            );
        }

        $table->modelAction(
            (new ResourceAction('Admin\EventController@fetchScore', 'Update score'))
                ->setRouteParameters($this->getShowRouteParameters($request))
                ->setQueryParameters($this->getShowQueryParameters($request))
                ->setCondition(function ($model) use ($request) {
                    return !empty($model->getSource()->quizwitz_report_id);
                })
        );

        return $table;
    }

    /**
     * @param Request $request
     * @param $eventId
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function fetchScore(Request $request, $eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $reportId = $event->quizwitz_report_id;

        $url = config('services.quizwitz.url') . 'report';
        $url .= '/' . $reportId;
        $url .= '?output=json&client=' . urlencode(config('services.quizwitz.apiClient'));

        $client = new Client();
        $response = $client->get($url);

        $data = json_decode($response->getBody(), true);
        $players = $data['players'];

        // Sort ze players
        usort($players, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        // dump all existing scores.
        $event->dumpScores();

        $position = 1;
        foreach ($players as $player) {
            $name = $player['name'];
            $score = $player['score'];

            $group = $event->attendees()->where('name', '=', $name)->first();
            $event->setScore($position, $name, $score, $group);

            $position ++;
        }

        return redirect()->back()
            ->with('message', 'Score was updated!')
            ->withInput();
    }

    /**
     * Get any parameters that might be required by the controller.
     * @param Request $request
     * @param $method
     * @return array
     */
    protected function getApiControllerParameters(Request $request, $method)
    {
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:
                return [
                    'organisation' => \Request::user()->getActiveOrganisation()->id
                ];

                break;
        }
    }

    /**
     * Get any parameters that might be required by the controller.
     * @param Request $request
     * @param $method
     * @return array
     */
    protected function getAuthorizeParameters(Request $request, $method)
    {
        switch ($method) {
            case Action::INDEX:
            case Action::CREATE:
                return [
                    \Request::user()->getActiveOrganisation()
                ];

                break;
        }
    }

    /**
     * @param $eventId
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportMembers($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $out = [];
        foreach ($event->attendees()->get() as $group) {
            /** @var Group $v */

            foreach ($group->members as $member) {
                /** @var GroupMember $member */
                if ($member->user) {
                    $out[] = [
                        $member->user->email,
                        $group->name,
                        $member->getName()
                    ];
                }
            }
        }

        return $this->outputCsv(
            str_slug($event->name) . '-members',
            ['email', 'team', 'username'],
            $out
        );
    }

    /**
     * @param $eventId
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportSales($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        list ($columns, $out) = $this->prepareExportSales($event);

        return $this->outputCsv(
            str_slug($event->name) . '-payments',
            $columns,
            $out
        );
    }

    public function exportSalesTimeline($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        list ($columns, $out) = $this->prepareExportSalesOverTime($event);

        return $this->outputCsv(
            str_slug($event->name) . '-salesOverTime',
            $columns,
            $out
        );
    }

    public function exportClearing($eventId)
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);
        list ($columns, $teams, $sumTotal, $columnsSum) = $this->prepareExportSales($event, true);

        $organisation = $event->organisation;

        $data = [
            'event' => $event,
            'columns' => $columns,
            'rows' => $teams,
            'totals' => $columnsSum,
            'total' => $sumTotal,
            'organisation' => $organisation
        ];

        if (\Request::get('pdf') === '0') {
            return view('pdf.clearing', $data);
        }

        $pdf = PDF
            ::loadView('pdf.clearing', $data)
            ->setPaper('a4', 'landscape');

        return $pdf->download('catlab-clearing-' . $event->id . '-' . Str::slug($event->name) . '.pdf');
    }

    /**
     * @param Event $event
     * @param bool $toMoney
     * @return array
     */
    protected function prepareExportSales(Event $event, $toMoney = false)
    {
        $orders = $event->orders()->accepted()->get();

        $columns = [
            'Id',
            'Reference',
            'Date',
            'Groupname',
            'Ticket Category',
            'Total paid'
        ];

        $sumTotal = 0;
        $columnsSum = [];
        $columnsSum['Total paid'] = 0;

        $columnData = [];
        foreach ($orders as $order) {
            /** @var Order $order */

            $data = $order->getOrderData(true);
            $total = $data['price'];

            $tmp = [
                'Id' => $order->id,
                'Reference' => $data['reference'],
                'Date' => $order->created_at->format('Y-m-d H:i:s'),
                'Ticket Category' => $order->ticketCategory->name,
                'Groupname' => $order->group ? $order->group->name : null,
                'Total paid' => $toMoney ? toMoney($total) : $total
            ];

            $index = 0;
            $columnNames = [ 'Ticket', 'Costs' ];

            // items and pricing
            foreach ($data['items'] as $item) {

                if (!isset($columnNames[$index])) {
                    break;
                }

                $column = $columnNames[$index];
                $vatColumn = $column . ' VAT';

                if (!in_array($column, $columns)) {
                    $columns[] = $column;
                    $columnsSum[$column] = 0;
                }

                if (!in_array($vatColumn, $columns)) {
                    $columns[] = $vatColumn;
                    $columnsSum[$vatColumn] = 0;
                }

                $price = $item['amount'] * $item['price'];
                $vat = $item['amount'] * $item['vat'];

                $tmp[$column] = $toMoney ? toMoney($price) : $price;
                $columnsSum[$column] += $price;

                $tmp[$vatColumn] = $toMoney ? toMoney($vat) : $vat;
                $columnsSum[$vatColumn] += $vat;

                $index ++;
            }

            $columnData[] = $tmp;

            $sumTotal += $total;
            $columnsSum['Total paid'] += $total;
        }

        $out = [];
        foreach ($columnData as $v) {
            $r = [];
            foreach ($columns as $c) {
                $r[$c] = isset($v[$c]) ? $v[$c] : null;
            }
            $out[] = $r;
        }

        if ($toMoney) {
            foreach ($columnsSum as $k => $v) {
                $columnsSum[$k] = toMoney($v);
            }
        }

        return [
            $columns,
            $out,
            $sumTotal,
            $columnsSum
        ];
    }

    /**
     * @param Event $event
     * @return array
     * @throws \Exception
     */
    protected function prepareExportSalesOverTime(Event $event)
    {
        $orders = $event
            ->orders()
            ->accepted()
            ->getQuery()
            ->select([
                \DB::raw('DATE(created_at) AS date'),
                \DB::raw('COUNT(id) AS sales')
            ])
            ->groupBy(\DB::raw('DATE(created_at)'))
            ->orderBy(\DB::raw('DATE(created_at)'))
            ->get()
        ;
        $columns = [
            'Date',
            'Sales',
            'Sum'
        ];

        if (count($orders) === 0) {
            return [];
        }

        $date = new \DateTime($orders->first()->date);
        $orders = $orders->keyBy('date');

        $out = [];
        $sum = 0;

        while ($date < $event->startDate) {

            $sales = $orders->get($date->format('Y-m-d'));
            if ($sales !== null) {
                $sales = $sales->sales;
            } else {
                $sales = 0;
            }

            $sum += $sales;
            $out[] = [
                $date->format('Y-m-d'),
                $sales,
                $sum
            ];

            $date->add(new \DateInterval('P1D'));
        }

        return [
            $columns,
            $out
        ];
    }

    /**
     * @param $name
     * @param $columns
     * @param $data
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    protected function outputCsv($name, $columns, $data)
    {
        $headers = [
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=' . $name . '.csv',
            'Expires'             => '0',
            'Pragma'              => 'public'
        ];

        return \Response::stream(
            function() use ($columns, $data) {
                $out = fopen('php://output', 'w');
                //fputcsv($out, $columns);

                foreach($data as $line)
                {
                    fputcsv($out, $line);
                }
                fclose($out);
            },
            200,
            $headers
        );
    }
}

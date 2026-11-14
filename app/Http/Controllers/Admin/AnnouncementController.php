<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Announcements shown on every company's dashboard. */
class AnnouncementController extends CrudController
{
    protected string $model = Announcement::class;

    protected string $route = 'admin.announcements';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Announcements';

    protected string $singular = 'Announcement';

    protected string $icon = 'bullhorn';

    protected array $search = ['title'];

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    /** Viewers read announcements; managers and super administrators write them. */
    public function callAction($method, $parameters)
    {
        abort_if($method !== 'index' && ! auth('admin')->user()->canDecide(), 403);

        return parent::callAction($method, $parameters);
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'title', 'label' => 'Title', 'col' => 8, 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'tone', 'label' => 'Kind', 'type' => 'select', 'col' => 4, 'options' => ['info' => __('Information'), 'warning' => __('Warning'), 'success' => __('Good news')], 'rules' => ['required', Rule::in(['info', 'warning', 'success'])], 'default' => 'info'],
            ['name' => 'body', 'label' => 'Text', 'type' => 'textarea', 'col' => 12, 'rules' => ['nullable', 'string', 'max:2000']],
            ['name' => 'starts_on', 'label' => 'Show from', 'type' => 'date', 'col' => 4, 'rules' => ['nullable', 'date']],
            ['name' => 'ends_on', 'label' => 'Show until', 'type' => 'date', 'col' => 4, 'rules' => ['nullable', 'date', 'after_or_equal:starts_on']],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'col' => 4, 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Title' => fn (Announcement $a) => $a->title.($a->is_active ? '' : ' ('.__('inactive').')'),
            'Show from' => fn (Announcement $a) => fmt_date($a->starts_on),
            'Show until' => fn (Announcement $a) => fmt_date($a->ends_on),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\DamageAssessmentRule;
use App\Models\MrCode;
use App\Http\Requests\StoreDamageAssessmentRuleRequest;
use App\Http\Requests\UpdateDamageAssessmentRuleRequest;
use Illuminate\Http\Request;

class DamageAssessmentRuleController extends Controller
{
    private function codes(): array
    {
        return [
            'locations'  => MrCode::ofType('location')->active()->orderBy('sort_order')->get(),
            'components' => MrCode::ofType('component')->active()->orderBy('sort_order')->get(),
            'damages'    => MrCode::ofType('damage')->active()->orderBy('sort_order')->get(),
            'repairs'    => MrCode::ofType('repair')->active()->orderBy('sort_order')->get(),
        ];
    }

    public function index(Request $request)
    {
        $query = DamageAssessmentRule::with('locationCode', 'componentCode', 'damageCode', 'repairCode');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('location_code_id')) {
            $query->where('location_code_id', $request->location_code_id);
        }
        if ($request->filled('component_code_id')) {
            $query->where('component_code_id', $request->component_code_id);
        }
        if ($request->filled('damage_code_id')) {
            $query->where('damage_code_id', $request->damage_code_id);
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->active === '1');
        }

        $rules = $query->orderBy('sort_order')->orderBy('name')->paginate(30)->withQueryString();

        return view('damage-assessment-rules.index', array_merge($this->codes(), [
            'rules' => $rules,
        ]));
    }

    public function create()
    {
        return view('damage-assessment-rules.create', $this->codes());
    }

    public function store(StoreDamageAssessmentRuleRequest $request)
    {
        $data = $request->validated();
        $data['is_active']  = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['created_by'] = auth()->id();

        DamageAssessmentRule::create($data);

        return redirect()->route('masters.damage-assessment-rules.index')
                         ->with('success', 'Assessment rule created successfully.');
    }

    public function edit(DamageAssessmentRule $damageAssessmentRule)
    {
        return view('damage-assessment-rules.edit', array_merge($this->codes(), [
            'rule' => $damageAssessmentRule,
        ]));
    }

    public function update(UpdateDamageAssessmentRuleRequest $request, DamageAssessmentRule $damageAssessmentRule)
    {
        $data = $request->validated();
        $data['is_active']  = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $damageAssessmentRule->update($data);

        return redirect()->route('masters.damage-assessment-rules.index')
                         ->with('success', 'Assessment rule updated.');
    }

    public function destroy(DamageAssessmentRule $damageAssessmentRule)
    {
        $damageAssessmentRule->delete();

        return redirect()->route('masters.damage-assessment-rules.index')
                         ->with('success', 'Rule deleted.');
    }

    public function toggleActive(DamageAssessmentRule $damageAssessmentRule)
    {
        $damageAssessmentRule->update(['is_active' => !$damageAssessmentRule->is_active]);
        $state = $damageAssessmentRule->fresh()->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Rule {$state}.");
    }

    public function search(Request $request)
    {
        $query = DamageAssessmentRule::active()
            ->with('locationCode', 'componentCode', 'damageCode', 'repairCode');

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }
        if ($request->filled('location_code_id')) {
            $query->where('location_code_id', $request->location_code_id);
        }
        if ($request->filled('component_code_id')) {
            $query->where('component_code_id', $request->component_code_id);
        }
        if ($request->filled('damage_code_id')) {
            $query->where('damage_code_id', $request->damage_code_id);
        }

        $rules = $query->orderBy('sort_order')->orderBy('name')->limit(200)->get();

        return response()->json([
            'rules' => $rules->map(fn($r) => [
                'id'               => $r->id,
                'name'             => $r->name,
                'location_code_id' => $r->location_code_id,
                'location_code'    => $r->locationCode?->code,
                'location_name'    => $r->locationCode?->name,
                'component_code_id'=> $r->component_code_id,
                'component_code'   => $r->componentCode?->code,
                'component_name'   => $r->componentCode?->name,
                'damage_code_id'   => $r->damage_code_id,
                'damage_code'      => $r->damageCode?->code,
                'damage_name'      => $r->damageCode?->name,
                'repair_code_id'   => $r->repair_code_id,
                'repair_code'      => $r->repairCode?->code,
                'repair_name'      => $r->repairCode?->name,
                'default_severity' => $r->default_severity,
                'description'      => $r->description,
            ]),
        ]);
    }
}

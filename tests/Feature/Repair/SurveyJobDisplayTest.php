<?php

namespace Tests\Feature\Repair;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Inquiry;
use App\Models\YardJob;
use App\Models\YardJobType;
use Tests\Support\FeatureTestCase;

/**
 * Guards the survey Job No / Job Type display fix: a survey linked to a yard job
 * must actually render the job number and job type on both the view and edit
 * screens (not merely render without error, which the smoke test already covers).
 */
class SurveyJobDisplayTest extends FeatureTestCase
{
    public function test_survey_show_and_edit_display_the_linked_job_number_and_type(): void
    {
        $this->actingAsSystemAdmin();

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create([
            'customer_id' => $customer->id, 'size' => '40', 'type_code' => 'HC',
        ]);

        $jobType = YardJobType::where('job_type_code', 'LADEN_IN')->firstOrFail();
        ['job_no' => $jobNo, 'job_seq' => $jobSeq] = YardJob::generateJobNo($jobType);
        $job = YardJob::create([
            'job_no' => $jobNo, 'job_seq' => $jobSeq,
            'job_type_id' => $jobType->id, 'job_type_code' => $jobType->job_type_code,
            'type_short_code' => $jobType->type_short_code, 'customer_id' => $customer->id,
            'status' => 'open', 'started_at' => now(), 'created_by' => auth()->id(),
        ]);

        $inquiry = Inquiry::create([
            'inquiry_no'   => 'INQ-JOBUI1',
            'yard_job_id'  => $job->id,
            'container_id' => $container->id,
            'container_no' => $container->container_no,
            'size'         => $container->size,
            'type_code'    => $container->type_code,
            'customer_id'  => $customer->id,
            'inquiry_type' => 'damage_survey',
            'status'       => 'open',
            'priority'     => 'normal',
            'created_by'   => auth()->id(),
        ]);

        // View screen shows the linked Job No + Job Type.
        $show = $this->get(route('surveys.show', $inquiry));
        $show->assertOk();
        $show->assertSee($job->job_no);
        $show->assertSee($jobType->job_type_name);

        // Edit screen shows them too (the fix added the badge to edit).
        $edit = $this->get(route('surveys.edit', $inquiry));
        $edit->assertOk();
        $edit->assertSee($job->job_no);
        $edit->assertSee($jobType->job_type_name);
    }
}

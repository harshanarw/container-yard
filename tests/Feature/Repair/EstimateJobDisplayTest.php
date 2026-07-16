<?php

namespace Tests\Feature\Repair;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Inquiry;
use App\Models\YardJob;
use App\Models\YardJobType;
use Tests\Support\FeatureTestCase;

/**
 * The estimate creation screen (opened from a survey) must show the linked
 * Job Number and Job Type — the earlier module-wide review missed this page.
 */
class EstimateJobDisplayTest extends FeatureTestCase
{
    public function test_estimate_create_screen_shows_the_surveys_job_number_and_type(): void
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
            'inquiry_no'   => 'INQ-EJD1',
            'yard_job_id'  => $job->id,
            'container_id' => $container->id,
            'container_no' => $container->container_no,
            'size'         => $container->size,
            'type_code'    => $container->type_code,
            'customer_id'  => $customer->id,
            'inquiry_type' => 'damage_survey',
            'status'       => 'open',
            'priority'     => 'normal',
        ]);

        $this->get(route('estimates.create', ['inquiry_id' => $inquiry->id]))
            ->assertOk()
            ->assertSee($job->job_no)
            ->assertSee($jobType->job_type_name);
    }
}

<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerEmailContact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerEmailContactController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:customers.edit');
    }

    public function store(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'category'     => ['required', 'string', Rule::in(array_keys(config('email_categories.customer')))],
            'email'        => ['required', 'email', 'max:255'],
            'label'        => ['nullable', 'string', 'max:100'],
            'address_type' => ['required', 'in:to,cc'],
        ]);

        $data['customer_id'] = $customer->id;
        $data['sort_order']  = CustomerEmailContact::where('customer_id', $customer->id)
            ->where('category', $data['category'])->max('sort_order') + 1;

        CustomerEmailContact::create($data);

        return back()->with('success', 'Email contact added.');
    }

    public function update(Request $request, Customer $customer, CustomerEmailContact $contact)
    {
        abort_if($contact->customer_id !== $customer->id, 404);

        $data = $request->validate([
            'email'        => ['required', 'email', 'max:255'],
            'label'        => ['nullable', 'string', 'max:100'],
            'address_type' => ['required', 'in:to,cc'],
            'is_active'    => ['boolean'],
        ]);

        // Unchecked checkboxes are not submitted — resolve explicitly so a
        // contact can actually be deactivated from the edit form.
        $data['is_active'] = $request->boolean('is_active');

        $contact->update($data);

        return back()->with('success', 'Email contact updated.');
    }

    public function destroy(Customer $customer, CustomerEmailContact $contact)
    {
        abort_if($contact->customer_id !== $customer->id, 404);

        $contact->delete();
        return back()->with('success', 'Email contact removed.');
    }

    /**
     * Bulk-replace one category's recipient list from two free-text fields
     * (TO and CC). Each accepts multiple addresses — one per line or
     * comma-separated — optionally in "Name <email>" form. Existing rows are
     * reconciled by address so labels and ids survive; addresses no longer
     * present are deleted. TO wins when the same address appears in both.
     */
    public function sync(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'category'  => ['required', 'string', Rule::in(array_keys(config('email_categories.customer')))],
            'to_emails' => ['nullable', 'string', 'max:5000'],
            'cc_emails' => ['nullable', 'string', 'max:5000'],
        ]);

        $category = $data['category'];

        // Build the desired set keyed by lower-cased email; TO takes precedence.
        $desired = [];
        foreach ($this->parseEmailLines($request->input('to_emails')) as $row) {
            $desired[strtolower($row['email'])] = $row + ['address_type' => 'to'];
        }
        foreach ($this->parseEmailLines($request->input('cc_emails')) as $row) {
            $key = strtolower($row['email']);
            if (isset($desired[$key])) {
                continue; // already a TO recipient — never also CC it
            }
            $desired[$key] = $row + ['address_type' => 'cc'];
        }

        $existing = CustomerEmailContact::where('customer_id', $customer->id)
            ->where('category', $category)
            ->get()
            ->keyBy(fn ($c) => strtolower($c->email));

        $sort = 0;
        foreach ($desired as $key => $row) {
            $attrs = [
                'address_type' => $row['address_type'],
                'is_active'    => true,
                'sort_order'   => $sort++,
            ];
            // Only overwrite the stored label when the user typed a new one,
            // so an existing label is preserved if the line is just an address.
            if ($row['label'] !== null) {
                $attrs['label'] = $row['label'];
            }

            if ($contact = $existing->get($key)) {
                $contact->update($attrs);
                $existing->forget($key);
            } else {
                CustomerEmailContact::create($attrs + [
                    'customer_id' => $customer->id,
                    'category'    => $category,
                    'email'       => $row['email'],
                ]);
            }
        }

        // Anything left in $existing was removed from the textareas.
        foreach ($existing as $contact) {
            $contact->delete();
        }

        $label = config("email_categories.customer.$category.label", ucfirst($category));

        return back()->with('success', "{$label} recipients updated.");
    }

    /**
     * Parse a free-text address field into [['email' => .., 'label' => ..], ..].
     * Splits on newlines and commas, accepts optional "Name <email>" form,
     * drops invalid addresses, and de-duplicates case-insensitively (first wins).
     *
     * @return array<int, array{email: string, label: ?string}>
     */
    private function parseEmailLines(?string $raw): array
    {
        $out  = [];
        $seen = [];

        foreach (preg_split('/[\r\n,]+/', (string) $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $label = null;
            $email = $line;
            if (preg_match('/^(.*?)\s*<\s*([^>]+)\s*>$/', $line, $m)) {
                $label = trim($m[1]) !== '' ? trim($m[1]) : null;
                $email = trim($m[2]);
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $key = strtolower($email);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $out[] = ['email' => $email, 'label' => $label];
        }

        return $out;
    }
}

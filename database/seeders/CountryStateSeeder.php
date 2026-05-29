<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\CountryState;
use Illuminate\Database\Seeder;

class CountryStateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSriLanka();
        $this->seedMalaysia();
        $this->seedSingapore();
        $this->seedIndia();
        $this->seedUAE();
        $this->seedSaudiArabia();
        $this->seedQatar();
        $this->seedBangladesh();
        $this->seedPakistan();
        $this->seedChina();
        $this->seedUK();
        $this->seedAustralia();
        $this->seedCanada();
        $this->seedUnitedStates();
        $this->seedIndonesia();
        $this->seedThailand();
        $this->seedPhilippines();
        $this->seedVietnam();
        $this->seedJapan();
        $this->seedSouthKorea();
        $this->seedGermany();
        $this->seedFrance();

        $this->command->info('Country states seeded successfully.');
    }

    // ── Sri Lanka ─────────────────────────────────────────────────────────────
    private function seedSriLanka(): void
    {
        $id = Country::where('iso2', 'LK')->value('id');
        if (! $id) return;

        $provinces = [
            ['Western Province',      'WP', 1, ['Colombo', 'Gampaha', 'Kalutara']],
            ['Central Province',      'CP', 2, ['Kandy', 'Matale', 'Nuwara Eliya']],
            ['Southern Province',     'SP', 3, ['Galle', 'Matara', 'Hambantota']],
            ['Northern Province',     'NP', 4, ['Jaffna', 'Kilinochchi', 'Mannar', 'Vavuniya', 'Mullaitivu']],
            ['Eastern Province',      'EP', 5, ['Batticaloa', 'Ampara', 'Trincomalee']],
            ['North Western Province','NWP',6, ['Kurunegala', 'Puttalam']],
            ['North Central Province','NCP',7, ['Anuradhapura', 'Polonnaruwa']],
            ['Uva Province',          'UP', 8, ['Badulla', 'Monaragala']],
            ['Sabaragamuwa Province', 'SGP',9, ['Ratnapura', 'Kegalle']],
        ];

        foreach ($provinces as $i => [$name, $code, $sort, $districts]) {
            $province = CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => 'province', 'sort_order' => $sort]
            );
            foreach ($districts as $j => $district) {
                CountryState::updateOrCreate(
                    ['country_id' => $id, 'name' => $district, 'parent_id' => $province->id],
                    ['type' => 'district', 'sort_order' => $j + 1]
                );
            }
        }
    }

    // ── Malaysia ──────────────────────────────────────────────────────────────
    private function seedMalaysia(): void
    {
        $id = Country::where('iso2', 'MY')->value('id');
        if (! $id) return;

        $states = [
            ['Johor', 'JHR', 'state'],
            ['Kedah', 'KDH', 'state'],
            ['Kelantan', 'KTN', 'state'],
            ['Melaka', 'MLK', 'state'],
            ['Negeri Sembilan', 'NSN', 'state'],
            ['Pahang', 'PHG', 'state'],
            ['Perak', 'PRK', 'state'],
            ['Perlis', 'PLS', 'state'],
            ['Pulau Pinang', 'PNG', 'state'],
            ['Sabah', 'SBH', 'state'],
            ['Sarawak', 'SWK', 'state'],
            ['Selangor', 'SGR', 'state'],
            ['Terengganu', 'TRG', 'state'],
            ['W.P. Kuala Lumpur', 'KUL', 'territory'],
            ['W.P. Labuan', 'LBN', 'territory'],
            ['W.P. Putrajaya', 'PJY', 'territory'],
        ];

        foreach ($states as $i => [$name, $code, $type]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => $type, 'sort_order' => $i + 1]
            );
        }
    }

    // ── Singapore ─────────────────────────────────────────────────────────────
    private function seedSingapore(): void
    {
        $id = Country::where('iso2', 'SG')->value('id');
        if (! $id) return;

        $regions = [
            ['Central Region', 'CR'],
            ['East Region', 'ER'],
            ['North Region', 'NR'],
            ['North-East Region', 'NER'],
            ['West Region', 'WR'],
        ];

        foreach ($regions as $i => [$name, $code]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => 'region', 'sort_order' => $i + 1]
            );
        }
    }

    // ── India ─────────────────────────────────────────────────────────────────
    private function seedIndia(): void
    {
        $id = Country::where('iso2', 'IN')->value('id');
        if (! $id) return;

        $states = [
            // States
            ['Andhra Pradesh', 'AP', 'state'],
            ['Arunachal Pradesh', 'AR', 'state'],
            ['Assam', 'AS', 'state'],
            ['Bihar', 'BR', 'state'],
            ['Chhattisgarh', 'CG', 'state'],
            ['Goa', 'GA', 'state'],
            ['Gujarat', 'GJ', 'state'],
            ['Haryana', 'HR', 'state'],
            ['Himachal Pradesh', 'HP', 'state'],
            ['Jharkhand', 'JH', 'state'],
            ['Karnataka', 'KA', 'state'],
            ['Kerala', 'KL', 'state'],
            ['Madhya Pradesh', 'MP', 'state'],
            ['Maharashtra', 'MH', 'state'],
            ['Manipur', 'MN', 'state'],
            ['Meghalaya', 'ML', 'state'],
            ['Mizoram', 'MZ', 'state'],
            ['Nagaland', 'NL', 'state'],
            ['Odisha', 'OD', 'state'],
            ['Punjab', 'PB', 'state'],
            ['Rajasthan', 'RJ', 'state'],
            ['Sikkim', 'SK', 'state'],
            ['Tamil Nadu', 'TN', 'state'],
            ['Telangana', 'TG', 'state'],
            ['Tripura', 'TR', 'state'],
            ['Uttar Pradesh', 'UP', 'state'],
            ['Uttarakhand', 'UK', 'state'],
            ['West Bengal', 'WB', 'state'],
            // Union Territories
            ['Andaman and Nicobar Islands', 'AN', 'union_territory'],
            ['Chandigarh', 'CH', 'union_territory'],
            ['Dadra and Nagar Haveli and Daman and Diu', 'DH', 'union_territory'],
            ['Delhi', 'DL', 'union_territory'],
            ['Jammu and Kashmir', 'JK', 'union_territory'],
            ['Ladakh', 'LA', 'union_territory'],
            ['Lakshadweep', 'LD', 'union_territory'],
            ['Puducherry', 'PY', 'union_territory'],
        ];

        foreach ($states as $i => [$name, $code, $type]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => $type, 'sort_order' => $i + 1]
            );
        }
    }

    // ── UAE ───────────────────────────────────────────────────────────────────
    private function seedUAE(): void
    {
        $id = Country::where('iso2', 'AE')->value('id');
        if (! $id) return;

        $emirates = [
            ['Abu Dhabi', 'AZ'],
            ['Dubai', 'DU'],
            ['Sharjah', 'SH'],
            ['Ajman', 'AJ'],
            ['Umm Al Quwain', 'UQ'],
            ['Ras Al Khaimah', 'RK'],
            ['Fujairah', 'FU'],
        ];

        foreach ($emirates as $i => [$name, $code]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => 'emirate', 'sort_order' => $i + 1]
            );
        }
    }

    // ── Saudi Arabia ──────────────────────────────────────────────────────────
    private function seedSaudiArabia(): void
    {
        $id = Country::where('iso2', 'SA')->value('id');
        if (! $id) return;

        $regions = [
            ['Riyadh', 'RD'], ['Makkah', 'MK'], ['Madinah', 'MD'], ['Qassim', 'QS'],
            ['Eastern Province', 'EP'], ['Asir', 'AS'], ['Tabuk', 'TB'], ['Hail', 'HL'],
            ['Northern Borders', 'NB'], ['Jazan', 'JZ'], ['Najran', 'NJ'],
            ['Al Bahah', 'BH'], ['Al Jawf', 'JF'],
        ];

        foreach ($regions as $i => [$name, $code]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => 'region', 'sort_order' => $i + 1]
            );
        }
    }

    // ── Qatar ─────────────────────────────────────────────────────────────────
    private function seedQatar(): void
    {
        $id = Country::where('iso2', 'QA')->value('id');
        if (! $id) return;

        $municipalities = [
            ['Doha', 'DA'], ['Al Khor', 'KH'], ['Al Shamal', 'MS'], ['Al Wakrah', 'WA'],
            ['Al Rayyan', 'RA'], ['Al Daayen', 'ZA'], ['Al Shahaniya', 'SH'], ['Umm Salal', 'US'],
        ];

        foreach ($municipalities as $i => [$name, $code]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => 'municipality', 'sort_order' => $i + 1]
            );
        }
    }

    // ── Bangladesh ────────────────────────────────────────────────────────────
    private function seedBangladesh(): void
    {
        $id = Country::where('iso2', 'BD')->value('id');
        if (! $id) return;

        $divisions = [
            ['Barishal', 'A'], ['Chattogram', 'B'], ['Dhaka', 'C'], ['Khulna', 'D'],
            ['Mymensingh', 'H'], ['Rajshahi', 'E'], ['Rangpur', 'F'], ['Sylhet', 'G'],
        ];

        foreach ($divisions as $i => [$name, $code]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => 'division', 'sort_order' => $i + 1]
            );
        }
    }

    // ── Pakistan ──────────────────────────────────────────────────────────────
    private function seedPakistan(): void
    {
        $id = Country::where('iso2', 'PK')->value('id');
        if (! $id) return;

        $provinces = [
            ['Punjab', 'PB', 'province'],
            ['Sindh', 'SD', 'province'],
            ['Khyber Pakhtunkhwa', 'KP', 'province'],
            ['Balochistan', 'BA', 'province'],
            ['Islamabad Capital Territory', 'IS', 'territory'],
            ['Azad Kashmir', 'AK', 'territory'],
            ['Gilgit-Baltistan', 'GB', 'territory'],
        ];

        foreach ($provinces as $i => [$name, $code, $type]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => $type, 'sort_order' => $i + 1]
            );
        }
    }

    // ── China ─────────────────────────────────────────────────────────────────
    private function seedChina(): void
    {
        $id = Country::where('iso2', 'CN')->value('id');
        if (! $id) return;

        $provinces = [
            // Direct-controlled municipalities
            ['Beijing', 'BJ', 'municipality'],
            ['Shanghai', 'SH', 'municipality'],
            ['Chongqing', 'CQ', 'municipality'],
            ['Tianjin', 'TJ', 'municipality'],
            // Provinces
            ['Guangdong', 'GD', 'province'],
            ['Jiangsu', 'JS', 'province'],
            ['Zhejiang', 'ZJ', 'province'],
            ['Shandong', 'SD', 'province'],
            ['Henan', 'HA', 'province'],
            ['Sichuan', 'SC', 'province'],
            ['Hubei', 'HB', 'province'],
            ['Hunan', 'HN', 'province'],
            ['Anhui', 'AH', 'province'],
            ['Fujian', 'FJ', 'province'],
            ['Hebei', 'HE', 'province'],
            ['Liaoning', 'LN', 'province'],
            ['Jilin', 'JL', 'province'],
            ['Heilongjiang', 'HL', 'province'],
            ['Shaanxi', 'SN', 'province'],
            ['Shanxi', 'SX', 'province'],
            ['Guizhou', 'GZ', 'province'],
            ['Yunnan', 'YN', 'province'],
            ['Jiangxi', 'JX', 'province'],
            ['Gansu', 'GS', 'province'],
            ['Qinghai', 'QH', 'province'],
            ['Hainan', 'HI', 'province'],
            // Autonomous regions
            ['Guangxi', 'GX', 'region'],
            ['Inner Mongolia', 'NM', 'region'],
            ['Xinjiang', 'XJ', 'region'],
            ['Tibet', 'XZ', 'region'],
            ['Ningxia', 'NX', 'region'],
            // Special Administrative Regions
            ['Hong Kong SAR', 'HK', 'territory'],
            ['Macau SAR', 'MO', 'territory'],
        ];

        foreach ($provinces as $i => [$name, $code, $type]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => $type, 'sort_order' => $i + 1]
            );
        }
    }

    // ── United Kingdom ────────────────────────────────────────────────────────
    private function seedUK(): void
    {
        $id = Country::where('iso2', 'GB')->value('id');
        if (! $id) return;

        $countries = [
            ['England', 'ENG', 'country'],
            ['Scotland', 'SCT', 'country'],
            ['Wales', 'WLS', 'country'],
            ['Northern Ireland', 'NIR', 'country'],
        ];

        foreach ($countries as $i => [$name, $code, $type]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => $type, 'sort_order' => $i + 1]
            );
        }
    }

    // ── Australia ─────────────────────────────────────────────────────────────
    private function seedAustralia(): void
    {
        $id = Country::where('iso2', 'AU')->value('id');
        if (! $id) return;

        $states = [
            ['New South Wales', 'NSW', 'state'],
            ['Victoria', 'VIC', 'state'],
            ['Queensland', 'QLD', 'state'],
            ['South Australia', 'SA', 'state'],
            ['Western Australia', 'WA', 'state'],
            ['Tasmania', 'TAS', 'state'],
            ['Australian Capital Territory', 'ACT', 'territory'],
            ['Northern Territory', 'NT', 'territory'],
        ];

        foreach ($states as $i => [$name, $code, $type]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => $type, 'sort_order' => $i + 1]
            );
        }
    }

    // ── Canada ────────────────────────────────────────────────────────────────
    private function seedCanada(): void
    {
        $id = Country::where('iso2', 'CA')->value('id');
        if (! $id) return;

        $provinces = [
            ['Alberta', 'AB', 'province'],
            ['British Columbia', 'BC', 'province'],
            ['Manitoba', 'MB', 'province'],
            ['New Brunswick', 'NB', 'province'],
            ['Newfoundland and Labrador', 'NL', 'province'],
            ['Nova Scotia', 'NS', 'province'],
            ['Ontario', 'ON', 'province'],
            ['Prince Edward Island', 'PE', 'province'],
            ['Quebec', 'QC', 'province'],
            ['Saskatchewan', 'SK', 'province'],
            ['Northwest Territories', 'NT', 'territory'],
            ['Nunavut', 'NU', 'territory'],
            ['Yukon', 'YT', 'territory'],
        ];

        foreach ($provinces as $i => [$name, $code, $type]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => $type, 'sort_order' => $i + 1]
            );
        }
    }

    // ── United States ─────────────────────────────────────────────────────────
    private function seedUnitedStates(): void
    {
        $id = Country::where('iso2', 'US')->value('id');
        if (! $id) return;

        $states = [
            ['Alabama','AL'],['Alaska','AK'],['Arizona','AZ'],['Arkansas','AR'],
            ['California','CA'],['Colorado','CO'],['Connecticut','CT'],['Delaware','DE'],
            ['Florida','FL'],['Georgia','GA'],['Hawaii','HI'],['Idaho','ID'],
            ['Illinois','IL'],['Indiana','IN'],['Iowa','IA'],['Kansas','KS'],
            ['Kentucky','KY'],['Louisiana','LA'],['Maine','ME'],['Maryland','MD'],
            ['Massachusetts','MA'],['Michigan','MI'],['Minnesota','MN'],['Mississippi','MS'],
            ['Missouri','MO'],['Montana','MT'],['Nebraska','NE'],['Nevada','NV'],
            ['New Hampshire','NH'],['New Jersey','NJ'],['New Mexico','NM'],['New York','NY'],
            ['North Carolina','NC'],['North Dakota','ND'],['Ohio','OH'],['Oklahoma','OK'],
            ['Oregon','OR'],['Pennsylvania','PA'],['Rhode Island','RI'],['South Carolina','SC'],
            ['South Dakota','SD'],['Tennessee','TN'],['Texas','TX'],['Utah','UT'],
            ['Vermont','VT'],['Virginia','VA'],['Washington','WA'],['West Virginia','WV'],
            ['Wisconsin','WI'],['Wyoming','WY'],['District of Columbia','DC'],
        ];

        foreach ($states as $i => [$name, $code]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => 'state', 'sort_order' => $i + 1]
            );
        }
    }

    // ── Indonesia ─────────────────────────────────────────────────────────────
    private function seedIndonesia(): void
    {
        $id = Country::where('iso2', 'ID')->value('id');
        if (! $id) return;

        $provinces = [
            ['Aceh','AC'],['Bali','BA'],['Banten','BT'],['Bengkulu','BE'],
            ['Central Java','JT'],['Central Kalimantan','KT'],['Central Sulawesi','ST'],
            ['East Java','JI'],['East Kalimantan','KI'],['East Nusa Tenggara','NT'],
            ['Gorontalo','GO'],['Jakarta','JK'],['Jambi','JA'],['Lampung','LA'],
            ['Maluku','MA'],['North Kalimantan','KU'],['North Maluku','MU'],
            ['North Sulawesi','SA'],['North Sumatra','SU'],['Papua','PA'],
            ['Riau','RI'],['Riau Islands','KR'],['South Kalimantan','KS'],
            ['South Sulawesi','SN'],['South Sumatra','SS'],['Southeast Sulawesi','SG'],
            ['West Java','JB'],['West Kalimantan','KB'],['West Nusa Tenggara','NB'],
            ['West Papua','PB'],['West Sulawesi','SR'],['West Sumatra','SB'],
            ['Yogyakarta','YO'],
        ];

        foreach ($provinces as $i => [$name, $code]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => 'province', 'sort_order' => $i + 1]
            );
        }
    }

    // ── Thailand ──────────────────────────────────────────────────────────────
    private function seedThailand(): void
    {
        $id = Country::where('iso2', 'TH')->value('id');
        if (! $id) return;

        $provinces = [
            'Bangkok','Amnat Charoen','Ang Thong','Bueng Kan','Buriram','Chachoengsao',
            'Chai Nat','Chaiyaphum','Chanthaburi','Chiang Mai','Chiang Rai','Chon Buri',
            'Chumphon','Kalasin','Kamphaeng Phet','Kanchanaburi','Khon Kaen','Krabi',
            'Lampang','Lamphun','Loei','Lop Buri','Mae Hong Son','Maha Sarakham',
            'Mukdahan','Nakhon Nayok','Nakhon Pathom','Nakhon Phanom','Nakhon Ratchasima',
            'Nakhon Sawan','Nakhon Si Thammarat','Nan','Narathiwat','Nong Bua Lamphu',
            'Nong Khai','Nonthaburi','Pathum Thani','Pattani','Phang Nga','Phatthalung',
            'Phayao','Phetchabun','Phetchaburi','Phichit','Phitsanulok','Phra Nakhon Si Ayutthaya',
            'Phrae','Phuket','Prachin Buri','Prachuap Khiri Khan','Ranong','Ratchaburi',
            'Rayong','Roi Et','Sa Kaeo','Sakon Nakhon','Samut Prakan','Samut Sakhon',
            'Samut Songkhram','Saraburi','Satun','Sing Buri','Sisaket','Songkhla',
            'Sukhothai','Suphan Buri','Surat Thani','Surin','Tak','Trang','Trat',
            'Ubon Ratchathani','Udon Thani','Uthai Thani','Uttaradit','Yala','Yasothon',
        ];

        foreach ($provinces as $i => $name) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['type' => 'province', 'sort_order' => $i + 1]
            );
        }
    }

    // ── Philippines ───────────────────────────────────────────────────────────
    private function seedPhilippines(): void
    {
        $id = Country::where('iso2', 'PH')->value('id');
        if (! $id) return;

        // Major regions (simplified)
        $regions = [
            ['Ilocos Region (Region I)', 'I'],
            ['Cagayan Valley (Region II)', 'II'],
            ['Central Luzon (Region III)', 'III'],
            ['Calabarzon (Region IV-A)', 'IVA'],
            ['Mimaropa (Region IV-B)', 'IVB'],
            ['Bicol Region (Region V)', 'V'],
            ['Western Visayas (Region VI)', 'VI'],
            ['Central Visayas (Region VII)', 'VII'],
            ['Eastern Visayas (Region VIII)', 'VIII'],
            ['Zamboanga Peninsula (Region IX)', 'IX'],
            ['Northern Mindanao (Region X)', 'X'],
            ['Davao Region (Region XI)', 'XI'],
            ['Soccsksargen (Region XII)', 'XII'],
            ['National Capital Region (NCR)', 'NCR'],
            ['Cordillera Administrative Region (CAR)', 'CAR'],
            ['Caraga (Region XIII)', 'XIII'],
            ['Bangsamoro (BARMM)', 'BARMM'],
        ];

        foreach ($regions as $i => [$name, $code]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => 'region', 'sort_order' => $i + 1]
            );
        }
    }

    // ── Vietnam ───────────────────────────────────────────────────────────────
    private function seedVietnam(): void
    {
        $id = Country::where('iso2', 'VN')->value('id');
        if (! $id) return;

        $provinces = [
            'An Giang','Ba Ria-Vung Tau','Bac Giang','Bac Kan','Bac Lieu','Bac Ninh',
            'Ben Tre','Binh Dinh','Binh Duong','Binh Phuoc','Binh Thuan','Ca Mau',
            'Can Tho','Cao Bang','Da Nang','Dak Lak','Dak Nong','Dien Bien',
            'Dong Nai','Dong Thap','Gia Lai','Ha Giang','Ha Nam','Ha Noi',
            'Ha Tinh','Hai Duong','Hai Phong','Hau Giang','Ho Chi Minh City',
            'Hoa Binh','Hung Yen','Khanh Hoa','Kien Giang','Kon Tum','Lai Chau',
            'Lam Dong','Lang Son','Lao Cai','Long An','Nam Dinh','Nghe An',
            'Ninh Binh','Ninh Thuan','Phu Tho','Phu Yen','Quang Binh','Quang Nam',
            'Quang Ngai','Quang Ninh','Quang Tri','Soc Trang','Son La','Tay Ninh',
            'Thai Binh','Thai Nguyen','Thanh Hoa','Thua Thien Hue','Tien Giang',
            'Tra Vinh','Tuyen Quang','Vinh Long','Vinh Phuc','Yen Bai',
        ];

        foreach ($provinces as $i => $name) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['type' => 'province', 'sort_order' => $i + 1]
            );
        }
    }

    // ── Japan ─────────────────────────────────────────────────────────────────
    private function seedJapan(): void
    {
        $id = Country::where('iso2', 'JP')->value('id');
        if (! $id) return;

        $prefectures = [
            'Hokkaido','Aomori','Iwate','Miyagi','Akita','Yamagata','Fukushima',
            'Ibaraki','Tochigi','Gunma','Saitama','Chiba','Tokyo','Kanagawa',
            'Niigata','Toyama','Ishikawa','Fukui','Yamanashi','Nagano','Shizuoka',
            'Aichi','Mie','Shiga','Kyoto','Osaka','Hyogo','Nara','Wakayama',
            'Tottori','Shimane','Okayama','Hiroshima','Yamaguchi',
            'Tokushima','Kagawa','Ehime','Kochi',
            'Fukuoka','Saga','Nagasaki','Kumamoto','Oita','Miyazaki','Kagoshima','Okinawa',
        ];

        foreach ($prefectures as $i => $name) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['type' => 'prefecture', 'sort_order' => $i + 1]
            );
        }
    }

    // ── South Korea ───────────────────────────────────────────────────────────
    private function seedSouthKorea(): void
    {
        $id = Country::where('iso2', 'KR')->value('id');
        if (! $id) return;

        $divisions = [
            ['Seoul', 'SE', 'special_city'],
            ['Busan', 'BS', 'metropolitan_city'],
            ['Daegu', 'DG', 'metropolitan_city'],
            ['Incheon', 'IC', 'metropolitan_city'],
            ['Gwangju', 'GJ', 'metropolitan_city'],
            ['Daejeon', 'DJ', 'metropolitan_city'],
            ['Ulsan', 'UL', 'metropolitan_city'],
            ['Sejong', 'SJ', 'special_autonomous_city'],
            ['Gyeonggi', 'GG', 'province'],
            ['Gangwon', 'GW', 'province'],
            ['North Chungcheong', 'CB', 'province'],
            ['South Chungcheong', 'CN', 'province'],
            ['North Jeolla', 'JB', 'province'],
            ['South Jeolla', 'JN', 'province'],
            ['North Gyeongsang', 'GB', 'province'],
            ['South Gyeongsang', 'GN', 'province'],
            ['Jeju', 'JJ', 'special_autonomous_province'],
        ];

        foreach ($divisions as $i => [$name, $code, $type]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => $type, 'sort_order' => $i + 1]
            );
        }
    }

    // ── Germany ───────────────────────────────────────────────────────────────
    private function seedGermany(): void
    {
        $id = Country::where('iso2', 'DE')->value('id');
        if (! $id) return;

        $states = [
            ['Baden-Württemberg','BW'],['Bavaria','BY'],['Berlin','BE'],
            ['Brandenburg','BB'],['Bremen','HB'],['Hamburg','HH'],
            ['Hesse','HE'],['Lower Saxony','NI'],['Mecklenburg-Vorpommern','MV'],
            ['North Rhine-Westphalia','NW'],['Rhineland-Palatinate','RP'],['Saarland','SL'],
            ['Saxony','SN'],['Saxony-Anhalt','ST'],['Schleswig-Holstein','SH'],['Thuringia','TH'],
        ];

        foreach ($states as $i => [$name, $code]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => 'state', 'sort_order' => $i + 1]
            );
        }
    }

    // ── France ────────────────────────────────────────────────────────────────
    private function seedFrance(): void
    {
        $id = Country::where('iso2', 'FR')->value('id');
        if (! $id) return;

        $regions = [
            ['Auvergne-Rhône-Alpes','ARA'],['Bourgogne-Franche-Comté','BFC'],
            ['Bretagne','BRE'],['Centre-Val de Loire','CVL'],['Corse','COR'],
            ['Grand Est','GES'],['Hauts-de-France','HDF'],['Île-de-France','IDF'],
            ['Normandie','NOR'],['Nouvelle-Aquitaine','NAQ'],['Occitanie','OCC'],
            ['Pays de la Loire','PDL'],['Provence-Alpes-Côte d\'Azur','PAC'],
        ];

        foreach ($regions as $i => [$name, $code]) {
            CountryState::updateOrCreate(
                ['country_id' => $id, 'name' => $name, 'parent_id' => null],
                ['code' => $code, 'type' => 'region', 'sort_order' => $i + 1]
            );
        }
    }
}

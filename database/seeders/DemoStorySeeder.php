<?php

namespace Database\Seeders;

use App\Models\Story;
use App\Services\ImageService;
use Illuminate\Database\Seeder;

class DemoStorySeeder extends Seeder
{
    public function run(): void
    {
        // Agar demo pehle se hai to dobara mat banao
        if (Story::where('slug', 'jaadui-jungle')->exists()) {
            return;
        }

        $story = Story::create([
            'title'       => 'जादुई जंगल की कहानी',
            'slug'        => 'jaadui-jungle',
            'description' => 'एक छोटे लड़के आरव और एक जादुई जंगल की रोमांचक कहानी — भाग दर भाग।',
            'status'      => 'published',
        ]);

        $parts = [
            [
                'sort_order'   => 1,
                'title'        => 'जंगल में पहला कदम',
                'body'         => "आरव एक छोटा सा लड़का था जो गाँव के किनारे रहता था। एक सुबह जब सूरज की पहली किरण पेड़ों से छनकर आई, तो उसने देखा कि जंगल के भीतर से एक सुनहरी रोशनी चमक रही है।\n\nउसकी जिज्ञासा उसे रोक न सकी। वह धीरे-धीरे उस रोशनी की ओर बढ़ने लगा। हर कदम के साथ जंगल और भी रहस्यमयी होता जा रहा था।",
                'image_prompt' => 'a young indian boy stepping into a magical glowing forest at sunrise, golden light, cinematic, storybook illustration',
            ],
            [
                'sort_order'   => 2,
                'title'        => 'बोलने वाला हिरण',
                'body'         => "जैसे ही आरव रोशनी के पास पहुँचा, उसने एक सुनहरे हिरण को देखा जिसकी आँखें तारों की तरह चमक रही थीं।\n\n\"डरो मत, आरव,\" हिरण ने कोमल आवाज़ में कहा। \"मैं इस जंगल का रक्षक हूँ। तुम्हारे लिए एक ज़रूरी काम है।\"\n\nआरव हैरान रह गया — एक हिरण जो बोल सकता है! उसका दिल तेज़ी से धड़क रहा था, पर उसकी आँखों में डर नहीं, उत्साह था।",
                'image_prompt' => 'a majestic golden deer with glowing star-like eyes in an enchanted forest, a small indian boy looking in wonder, magical, storybook illustration',
            ],
        ];

        $imageService = app(ImageService::class);

        foreach ($parts as $data) {
            $part = $story->parts()->create($data);

            try {
                $part->update(['image_path' => $imageService->generate($data['image_prompt'])]);
                $this->command?->info("भाग {$data['sort_order']} की इमेज बन गई।");
            } catch (\Throwable $e) {
                $this->command?->warn("भाग {$data['sort_order']} की इमेज नहीं बनी: " . $e->getMessage());
            }
        }
    }
}

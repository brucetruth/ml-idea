<?php

declare(strict_types=1);

namespace ML\IDEA\Vision;

use ML\IDEA\Vision\Analyzers\ColorPaletteAnalyzer;
use ML\IDEA\Vision\Analyzers\SkinToneHeuristicAnalyzer;

final class ImageContentAnalyzer
{
    public function __construct(
        private readonly ColorPaletteAnalyzer $paletteAnalyzer = new ColorPaletteAnalyzer(),
        private readonly SkinToneHeuristicAnalyzer $skinAnalyzer = new SkinToneHeuristicAnalyzer(),
    ) {
    }

    /**
     * @param array<int, array{0:float|int,1:float|int,2:float|int}> $rgbSamples
     * @return array{palette: array<int, array{cluster:int, rgb: array{0:int,1:int,2:int}, hex:string, percentage:float}>, skin_analysis: array{skin_ratio: float, non_skin_ratio: float, risk_level: string, total_samples: int}}
     */
    public function analyze(array $rgbSamples): array
    {
        return [
            'palette' => $this->paletteAnalyzer->analyze($rgbSamples)['palette'],
            'skin_analysis' => $this->skinAnalyzer->analyze($rgbSamples),
        ];
    }
}

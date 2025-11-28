<?php

class FamilyTree {
    private $members = [];
    private $relations = [];
    private $width;
    private $height;

    public function __construct($width = 1200, $height = 800) {
        $this->width = $width;
        $this->height = $height;
    }

    public function addMember($id, $name, $class = '') {
        $this->members[$id] = [
            'id' => $id,
            'name' => $name,
            'class' => $class,
            'x' => 0,
            'y' => 0
        ];
    }

    // type: 'child' or 'spouse'
    public function addRelation($fromId, $toId, $type = 'child') {
        $this->relations[] = [
            'from' => $fromId,
            'to' => $toId,
            'type' => $type
        ];
    }

    private function inferGenerations(): array {
        // Build adjacency for spouse propagation
        $spouseAdj = [];
        foreach ($this->relations as $r) {
            if ($r['type'] === 'spouse') {
                $spouseAdj[$r['from']][] = $r['to'];
                $spouseAdj[$r['to']][] = $r['from'];
            }
        }

        $siblingAdj = [];
        foreach ($this->relations as $r) {
            if ($r['type'] === 'sibling') {
                $siblingAdj[$r['from']][] = $r['to'];
                $siblingAdj[$r['to']][] = $r['from'];
            }
        }

        // Seed generations with child edges: child = parent + 1
        $gen = [];
        $changed = true;
        for ($pass = 0; $pass < 8 && $changed; $pass++) {
            $changed = false;
            foreach ($this->relations as $r) {
                if ($r['type'] !== 'child') continue;
                $p = $r['from'];
                $c = $r['to'];
                if (!isset($gen[$p])) $gen[$p] = 0;
                $newGen = $gen[$p] + 1;
                if (!isset($gen[$c]) || $gen[$c] < $newGen) {
                    $gen[$c] = $newGen;
                    $changed = true;
                }
            }
            // Propagate equal generations across spouse connections
            foreach ($spouseAdj as $a => $neis) {
                foreach ($neis as $b) {
                    if (isset($gen[$a]) && !isset($gen[$b])) {
                        $gen[$b] = $gen[$a];
                        $changed = true;
                    } elseif (!isset($gen[$a]) && isset($gen[$b])) {
                        $gen[$a] = $gen[$b];
                        $changed = true;
                    } elseif (isset($gen[$a], $gen[$b])) {
                        // Keep them equal by pulling to the higher known gen
                        $m = max($gen[$a], $gen[$b]);
                        if ($gen[$a] !== $m || $gen[$b] !== $m) {
                            $gen[$a] = $m;
                            $gen[$b] = $m;
                            $changed = true;
                        }
                    }
                }
            }

            // Propagate equal generations across sibling connections
            foreach ($siblingAdj as $a => $neis) {
                foreach ($neis as $b) {
                    if (isset($gen[$a]) && !isset($gen[$b])) {
                        $gen[$b] = $gen[$a];
                        $changed = true;
                    } elseif (!isset($gen[$a]) && isset($gen[$b])) {
                        $gen[$a] = $gen[$b];
                        $changed = true;
                    } elseif (isset($gen[$a], $gen[$b])) {
                        $m = max($gen[$a], $gen[$b]);
                        if ($gen[$a] !== $m || $gen[$b] !== $m) {
                            $gen[$a] = $gen[$b] = $m;
                            $changed = true;
                        }
                    }
                }
            }
        }

        // Fallback: any unassigned member gets gen 0
        foreach ($this->members as $id => $_) {
            if (!isset($gen[$id])) $gen[$id] = 0;
        }

        return $gen;
    }

    private function layout() {
        // Tunables
        $generationSpacing = 150; // vertical distance between generations
        $minStepX = 120;          // minimum horizontal step per element
        $boxWidth = 100;          // must match render box width
        $spouseGap = 40;          // desired gap between spouses
        $leftMargin = 100;

        // 1) Infer generations with spouse propagation
        $gen = $this->inferGenerations();

        // 2) Group members by generation
        $groups = [];
        foreach ($this->members as $id => $_) {
            $g = $gen[$id] ?? 0;
            if (!isset($groups[$g])) $groups[$g] = [];
            $groups[$g][] = $id;
        }

        // 3) Initial placement per generation (fixed y, increasing x)
        foreach ($groups as $g => $ids) {
            $y = 100 + ($g * $generationSpacing);
            sort($ids);
            $x = $leftMargin;
            foreach ($ids as $id) {
                $this->members[$id]['y'] = $y;
                $this->members[$id]['x'] = $x;
                $x += $minStepX;
            }
        }

        // 4) Compress spouses horizontally and force same y
        foreach ($this->relations as $r) {
            if ($r['type'] !== 'spouse') continue;
            $a =& $this->members[$r['from']];
            $b =& $this->members[$r['to']];

            // Force same generation y
            $gA = $gen[$r['from']] ?? 0;
            $gB = $gen[$r['to']] ?? $gA;
            $gSame = max($gA, $gB);
            $ySame = 100 + ($gSame * $generationSpacing);
            $a['y'] = $ySame;
            $b['y'] = $ySame;

            // Compress horizontally to spouseGap around midpoint
            $mid = ($a['x'] + $b['x']) / 2;
            $a['x'] = $mid - $spouseGap / 2;
            $b['x'] = $mid + $spouseGap / 2;
        }

        // 5) Resolve collisions per generation (left-to-right spacing)
        foreach ($groups as $g => $ids) {
            $y = 100 + ($g * $generationSpacing);
            usort($ids, function($i, $j) {
                return ($this->members[$i]['x'] <=> $this->members[$j]['x']) ?: ($i <=> $j);
            });
            $x = $leftMargin;
            foreach ($ids as $id) {
                $cur = max($this->members[$id]['x'], $x);
                $this->members[$id]['x'] = $cur;
                $this->members[$id]['y'] = $y;
                $x = $cur + $minStepX;
            }
        }

        // 6) Light centering of children under parents, then re-enforce spacing
        $childrenByGen = [];
        foreach ($this->relations as $r) {
            if ($r['type'] !== 'child') continue;
            $p = $r['from'];
            $c = $r['to'];
            $pg = $gen[$p] ?? 0;
            $cg = $gen[$c] ?? ($pg + 1);
            if (!isset($childrenByGen[$cg])) $childrenByGen[$cg] = [];
            $childrenByGen[$cg][] = [$p, $c];
        }

        foreach ($childrenByGen as $cg => $pairs) {
            $targets = [];
            foreach ($pairs as [$p, $c]) {
                $targets[$c][] = $this->members[$p]['x'];
            }
            foreach ($targets as $childId => $parentXs) {
                $this->members[$childId]['x'] = array_sum($parentXs) / count($parentXs);
            }
            // Re-enforce spacing for that generation
            $ids = $groups[$cg] ?? [];
            usort($ids, function($i, $j) {
                return ($this->members[$i]['x'] <=> $this->members[$j]['x']) ?: ($i <=> $j);
            });
            $x = $leftMargin;
            foreach ($ids as $id) {
                $cur = max($this->members[$id]['x'], $x);
                $this->members[$id]['x'] = $cur;
                $x = $cur + $minStepX;
            }
        }

        // Store generation indices for render use
        $this->computedGen = $gen;
        $this->generationSpacing = $generationSpacing;
        $this->boxWidth = $boxWidth;
    }

    public function render() {
        $this->layout();
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$this->width}' height='{$this->height}'>";

        $lineColor = '#444';
        $lineWidth = 2;

        // Precompute text wrapping and boxHeight for all members
        $fontSize = 12;
        $lineHeight = 14;
        $padding = 10;
        $maxCharsPerLine = 16;

        $linesById = [];
        $boxHeightById = [];
        foreach ($this->members as $id => $m) {
            // Wrap name
            $words = explode(' ', $m['name']);
            $lines = [];
            $line = '';
            foreach ($words as $word) {
                if (strlen($line . ' ' . $word) <= $maxCharsPerLine) {
                    $line .= ($line ? ' ' : '') . $word;
                } else {
                    if ($line !== '') $lines[] = $line;
                    $line = $word;
                }
            }
            if ($line !== '') $lines[] = $line;

            $linesById[$id] = $lines;
            $boxHeightById[$id] = max(40, count($lines) * $lineHeight + $padding);
            // Persist for later use in connectors
            $this->members[$id]['boxHeight'] = $boxHeightById[$id];
        }

        // Compute generation line positions based on actual box bottoms
        $genBottoms = []; // genIndex => max bottom y of that generation
        foreach ($this->members as $id => $m) {
            $g = $this->computedGen[$id] ?? 0;
            $bottom = $m['y'] + $boxHeightById[$id];
            if (!isset($genBottoms[$g]) || $bottom > $genBottoms[$g]) {
                $genBottoms[$g] = $bottom;
            }
        }

        // Draw generation lines and vertical stubs
        $genSpans = []; // genIndex => array of xMid
        $genLineY = []; // genIndex => y position of the generation line
        $lineGutter = 8; // pixels below the tallest box in the generation

        foreach ($genBottoms as $g => $maxBottomY) {
            $genLineY[$g] = $maxBottomY + $lineGutter;
        }

        // Stubs from each box bottom down to its generation line
        foreach ($this->members as $id => $m) {
            $g = $this->computedGen[$id] ?? 0;
            $xMid = $m['x'] + ($this->boxWidth / 2);
            $yBottom = $m['y'] + $boxHeightById[$id];
            $yLine = $genLineY[$g];

            // Stub
            $svg .= "<line x1='{$xMid}' y1='{$yBottom}' x2='{$xMid}' y2='{$yLine}' stroke='{$lineColor}' stroke-width='{$lineWidth}' />";

            // Track span for generation line
            $genSpans[$g][] = $xMid;
        }

        // Draw generation lines (spanning all members of that generation)
        foreach ($genSpans as $g => $xs) {
            if (empty($xs)) continue;
            $yLine = $genLineY[$g];
            $x1 = min($xs) - 10;
            $x2 = max($xs) + 10;
            $svg .= "<line x1='{$x1}' y1='{$yLine}' x2='{$x2}' y2='{$yLine}' stroke='{$lineColor}' stroke-width='{$lineWidth}' />";
        }

        // Combined parent-child connectors using actual bottoms/tops
        $genLinks = []; // pg => cg => pairs
        foreach ($this->relations as $r) {
            if ($r['type'] !== 'child') continue;
            $pg = $this->computedGen[$r['from']] ?? 0;
            $cg = $this->computedGen[$r['to']] ?? ($pg + 1);
            $genLinks[$pg][$cg][] = [$r['from'], $r['to']];
        }

        foreach ($genLinks as $pg => $childGroups) {
            foreach ($childGroups as $cg => $pairs) {
                $parentXs = [];
                $childXs = [];
                foreach ($pairs as [$p, $c]) {
                    $parentXs[] = $this->members[$p]['x'] + ($this->boxWidth / 2);
                    $childXs[]  = $this->members[$c]['x'] + ($this->boxWidth / 2);
                }

                // Parent bottom is the generation line of the parent gen (cleaner connector)
                $yParentBottom = $genLineY[$pg];
                // Child top is the top edge of child boxes
                // We can use the generation baseline y for children because boxes sit at that y
                // but respect individual box top (all boxes share the same top y per generation)
                $yChildTop = 100 + ($cg * $this->generationSpacing);

                $span = array_merge($parentXs, $childXs);
                $xMid = (min($span) + max($span)) / 2;

                // Main vertical line between parent gen line and child top
                $svg .= "<line x1='{$xMid}' y1='{$yParentBottom}' x2='{$xMid}' y2='{$yChildTop}' stroke='{$lineColor}' stroke-width='{$lineWidth}' />";

                // Horizontal stubs from parents to the vertical
                foreach ($parentXs as $px) {
                    $svg .= "<line x1='{$px}' y1='{$yParentBottom}' x2='{$xMid}' y2='{$yParentBottom}' stroke='{$lineColor}' stroke-width='{$lineWidth}' />";
                }
                // Horizontal stubs from the vertical to children
                foreach ($childXs as $cx) {
                    $svg .= "<line x1='{$cx}' y1='{$yChildTop}' x2='{$xMid}' y2='{$yChildTop}' stroke='{$lineColor}' stroke-width='{$lineWidth}' />";
                }
            }
        }

        // Dotted spouse lines
        foreach ($this->relations as $r) {
            if ($r['type'] !== 'spouse') continue;
            $a = $this->members[$r['from']];
            $b = $this->members[$r['to']];
            $x1 = $a['x'] + ($this->boxWidth / 2);
            $x2 = $b['x'] + ($this->boxWidth / 2);
            $y  = $a['y'] + 20; // mid-height of base 40 box; looks good even with variable heights
            $svg .= "<line x1='{$x1}' y1='{$y}' x2='{$x2}' y2='{$y}' stroke='#666' stroke-width='2' stroke-dasharray='4,2' />";
        }

        // Member boxes and labels (render last so they sit on top)
        foreach ($this->members as $id => $m) {
            $lines = $linesById[$id];
            $boxHeight = $boxHeightById[$id];

            $classAttr = $m['class'] ? "class='{$m['class']}'" : '';
            $svg .= "<g {$classAttr} data-id='{$m['id']}'>";
            $svg .= "<rect x='{$m['x']}' y='{$m['y']}' width='{$this->boxWidth}' height='{$boxHeight}' rx='6' ry='6' fill='#fff' stroke='#333' />";

            // Vertically centered, with +4px visual tweak
            $textY = $m['y'] + ($boxHeight / 2) - (($lineHeight * (count($lines) - 1)) / 2) + 4;
            $svg .= "<text x='" . ($m['x'] + ($this->boxWidth / 2)) . "' y='{$textY}' font-size='{$fontSize}' fill='#111' text-anchor='middle'>";
            foreach ($lines as $i => $txt) {
                $dy = ($i === 0) ? '0' : $lineHeight;
                $svg .= "<tspan x='" . ($m['x'] + ($this->boxWidth / 2)) . "' dy='{$dy}'>{$txt}</tspan>";
            }
            $svg .= "</text>";
            $svg .= "</g>";
        }

        $svg .= "</svg>";
        return $svg;
    }
}

?>
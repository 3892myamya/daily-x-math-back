<?php

namespace App\Data;

use Exception;

class Field
{
    /** @var array<array<array<int>>> 3x3 の各マスが候補リストを持つ */
    public array $matrix;

    /** @var array<array<Operator>> 3x2 の横方向の計算に使う演算子 */
    public array $yokoFugo;

    /** @var array<array<Operator>> 2x3 の縦方向の計算に使う演算子 */
    public array $tateFugo;

    /** @var array<int> 長さ3（横方向の答え） */
    public array $yokoKotae;

    /** @var array<int> 長さ3（縦方向の答え） */
    public array $tateKotae;

    /** @var int 解法適用回数 */
    public int $trycount;

    /**
     * @param array<array<int>> $matrix 3x3（各マスは候補配列）
     * @param array<array<Operator>> $yokoFugo 3x2
     * @param array<array<Operator>> $tateFugo 2x3
     * @param array<int> $yokoKotae 長さ3
     * @param array<int> $tateKotae 長さ3
     */
    public function __construct(
        array $matrix,
        array $yokoFugo,
        array $tateFugo,
        array $yokoKotae,
        array $tateKotae,
    ) {
        $this->matrix = $matrix;
        $this->yokoFugo = $yokoFugo;
        $this->tateFugo = $tateFugo;
        $this->yokoKotae = $yokoKotae;
        $this->tateKotae = $tateKotae;
        $this->trycount = 0;

        $this->validate();
    }

    /**
     * matrix は空、他の4つは引数で受け取るコンストラクタ
     */
    public static function withEmptyMatrix(
        array $yokoFugo,
        array $tateFugo,
        array $yokoKotae,
        array $tateKotae
    ): self {
        $all = [1, 2, 3, 4, 5, 6, 7, 8, 9];
        $emptyMatrix = [
            [$all, $all, $all],
            [$all, $all, $all],
            [$all, $all, $all],
        ];
        return new self(
            $emptyMatrix,
            $yokoFugo,
            $tateFugo,
            $yokoKotae,
            $tateKotae
        );
    }
    public function questionSolve(): bool
    {
        try {
            while (!$this->isSolved()) {
                // ループ開始時点の盤面を記録
                $before = serialize($this->matrix);
                // まず通常の論理処理で解く
                if (!$this->solve()) {
                    return false; // 矛盾
                }
                if ($before !== serialize($this->matrix)) {
                    continue; // 変化 → solve に戻る
                }
                // 深さ0で仮定チェック（候補除外）
                if (!$this->tryAssumption(0)) {
                    return false;
                }
                if ($before !== serialize($this->matrix)) {
                    continue; // 変化 → solve に戻る
                }
                // 深さ1で仮定チェック
                if (!$this->tryAssumption(1)) {
                    return false;
                }
                if ($before !== serialize($this->matrix)) {
                    continue; // 変化 → solve に戻る
                }
                // 深さ2で仮定チェック
                if (!$this->tryAssumption(2)) {
                    return false;
                }
                if ($before !== serialize($this->matrix)) {
                    continue; // 変化 → solve に戻る
                }
                // 4つ完了し、どこも更新なし → 完全に行き詰まり or 解けた
                return $this->isSolved();
            }
        } catch (Exception $e) {
            return false;
        }
        // for debug
        // echo $this;
        return true;
    }

    public function tryAssumption(int $depth): bool
    {
        while (true) {
            $this->trycount = $this->trycount + 1;
            if ($this->trycount > 5) {
                throw new Exception;
            }
            $before = serialize($this->matrix);  // 変更前の状態を保存
            // matrix を探索
            for ($y = 0; $y < 3; $y++) {
                for ($x = 0; $x < 3; $x++) {
                    $candidates = $this->matrix[$y][$x];
                    // 候補が2つ以上ある場合に仮定処理
                    if (count($candidates) >= 2) {
                        foreach ($candidates as $candidate) {
                            // --- deep copy オブジェクト全体 ---
                            $clone = unserialize(serialize($this));
                            // --- 仮置き ---
                            $clone->matrix[$y][$x] = [$candidate];
                            $isOk = $clone->solve();
                            if ($isOk && $depth > 0) {
                                // 仮定が矛盾なし → もう一段階深く調べる
                                $isOk = $clone->tryAssumption($depth - 1);
                            }
                            if (!$isOk) {
                                // 仮定が矛盾 → 元の matrix の候補から削除
                                $this->removeCandidate($y, $x, $candidate);
                                // 候補を削除した結果、0 になったら矛盾
                                if (count($this->matrix[$y][$x]) === 0) {
                                    return false;
                                }
                            }
                        }
                    }
                }
            }
            // --- after チェック ---
            $after = serialize($this->matrix);
            if ($before === $after) {
                // 状態が変わっていない → もう更新は発生しない
                return true;
            }
            // 状態が変化 → while ループの最初に戻ってもう一度探索を行う
        }
    }

    private function removeCandidate(int $y, int $x, int $candidate): void
    {
        $this->matrix[$y][$x] = array_values(
            array_filter($this->matrix[$y][$x], fn($v) => $v !== $candidate)
        );
    }

    /**
     * パズルを解くメイン処理
     * numberSolve() と kotaeSolve() を繰り返す
     */
    public function solve(): bool
    {
        while (true) {
            // 前回の状態を保存
            $before = serialize($this->matrix);
            // ① 数字候補の単純消去（確定値を候補から除外）
            if (!$this->numberSolve()) {
                return false;
            }
            if ($before !== serialize($this->matrix)) {
                continue; // 変化 → solve に戻る
            }
            // ② 横・縦の答えから確定値を導く
            if (!$this->kotaeSolve()) {
                return false;
            }
            if ($before !== serialize($this->matrix)) {
                continue; // 変化 → solve に戻る
            }
            // 変化なし → ループ終了
            return true;
        }
    }

    /**
     * 解けているかどうか
     */
    public function isSolved(): bool
    {
        foreach ($this->matrix as $row) {
            foreach ($row as $cell) {
                if (count($cell) !== 1) {
                    return false;
                }
            }
        }
        return $this->solve();
    }

    /**
     * numberSolve()
     * - matrix 内の「確定している数値」を他の候補から消す
     * - 候補がなくなってしまったマスがあれば、falseを返す
     */
    private function numberSolve(): bool
    {
        // 1. まず全確定値を収集（候補が 1 個のマス）
        $fixedValues = [];

        for ($y = 0; $y < 3; $y++) {
            for ($x = 0; $x < 3; $x++) {
                if (count($this->matrix[$y][$x]) === 1) {
                    $fixedValues[] = $this->matrix[$y][$x][0];
                }
            }
        }

        // 2. 確定値を他の候補から除外
        for ($y = 0; $y < 3; $y++) {
            for ($x = 0; $x < 3; $x++) {

                // 確定しているマス自身はスキップ
                if (count($this->matrix[$y][$x]) === 1) {
                    continue;
                }

                // 候補から確定値をすべて除外
                $this->matrix[$y][$x] = array_values(array_diff($this->matrix[$y][$x], $fixedValues));

                if (count($this->matrix[$y][$x]) === 0) {
                    return false;
                }
            }
        }

        return true;
    }


    /**
     * kotaeSolve()
     * - 横 3 行 & 縦 3 列 の計 6 本の式について、
     *   2 マス確定なら残り 1 マスの候補を絞り込む
     * - 絞り込んだ結果、候補が 0 になった場合 false を返す
     */
    private function kotaeSolve(): bool
    {
        // 横方向の 3 行を処理（y = 0〜2）
        for ($y = 0; $y < 3; $y++) {
            $ok = $this->solveLine(
                [$this->matrix[$y][0], $this->matrix[$y][1], $this->matrix[$y][2]],
                $this->yokoFugo[$y][0],
                $this->yokoFugo[$y][1],
                $this->yokoKotae[$y],
                function ($xIndex, $newCandidates) use ($y) {
                    $this->matrix[$y][$xIndex] = $newCandidates;
                }
            );
            if (!$ok) return false;
        }

        // 縦方向の 3 列を処理（x = 0〜2）
        for ($x = 0; $x < 3; $x++) {
            $ok = $this->solveLine(
                [$this->matrix[0][$x], $this->matrix[1][$x], $this->matrix[2][$x]],
                $this->tateFugo[0][$x],
                $this->tateFugo[1][$x],
                $this->tateKotae[$x],
                function ($yIndex, $newCandidates) use ($x) {
                    $this->matrix[$yIndex][$x] = $newCandidates;
                }
            );
            if (!$ok) return false;
        }

        return true;
    }


    /**
     * solveLine()
     * - 3 マスの line = [A, B, C] に対し
     *   (A f1 B) f2 C が target になるような値で絞り込む
     *
     * @param array<array<int>> $cells   [cell0, cell1, cell2]
     * @param Operator $op1              f1
     * @param Operator $op2              f2
     * @param int $target                目標値
     * @param callable $assign           (index, candidates) → matrix へ反映
     */
    private function solveLine(array $cells, Operator $op1, Operator $op2, int $target, callable $assign): bool
    {
        // 2 マス確定のときだけ処理
        $fixedCount = 0;
        $fixedIndices = [];

        for ($i = 0; $i < 3; $i++) {
            if (count($cells[$i]) === 1) {
                $fixedCount++;
                $fixedIndices[] = $i;
            }
        }

        if ($fixedCount !== 2) {
            // 2 マス確定ではない → 何もしない
            return true;
        }

        // 未確定の index を特定
        $freeIndex = 3 - array_sum($fixedIndices);

        $A = $cells[0];
        $B = $cells[1];
        $C = $cells[2];

        // f(A, B)
        $apply = function (int $v1, Operator $op, int $v2): int {
            return match ($op) {
                Operator::ADD => $v1 + $v2,
                Operator::SUB => $v1 - $v2,
                Operator::MUL => $v1 * $v2,
                Operator::DIV => $v1 / $v2,
            };
        };

        $candidates = [];

        // freeIndex に入る候補を総当たりで確認
        foreach ($cells[$freeIndex] as $cand) {

            // その候補を仮に入れてみて値を計算
            $vals = [$A, $B, $C];
            $vals[$freeIndex] = [$cand];

            $v1 = $vals[0][0];
            $v2 = $vals[1][0];
            $v3 = $vals[2][0];

            $res = $apply($apply($v1, $op1, $v2), $op2, $v3);

            if ($res === $target) {
                $candidates[] = $cand;
            }
        }

        // 候補が 0 → 矛盾
        if (count($candidates) === 0) {
            return false;
        }

        // 候補を更新
        $assign($freeIndex, $candidates);

        return true;
    }

    /**
     * 解ける問題を生成する
     */
    public static function generate(string $baseSeed): self
    {
        // まず問題生成
        $salt = 0;
        $candQuestion = self::random($baseSeed . $salt);
        while (true) {
            $isOk = true;
            foreach ($candQuestion->yokoKotae as $v) {
                if ((int)$v != $v || $v < 0 || $v > 50) {
                    $isOk = false;
                    break;
                }
            }
            if (!$isOk) {
                $salt = $salt + 1;
                $candQuestion = self::random($baseSeed . $salt);
                continue;
            }
            foreach ($candQuestion->tateKotae as $v) {
                if ((int)$v != $v || $v < 0 || $v > 50) {
                    $isOk = false;
                    break;
                }
            }
            if (!$isOk) {
                $salt = $salt + 1;
                $candQuestion = self::random($baseSeed . $salt);
                continue;
            }
            // 元に戻す
            $all = [1, 2, 3, 4, 5, 6, 7, 8, 9];
            $candQuestion->matrix = [
                [$all, $all, $all],
                [$all, $all, $all],
                [$all, $all, $all],
            ];
            $solved = $candQuestion->questionSolve();
            if (!$solved) {
                $salt = $salt + 1;
                $candQuestion = self::random($baseSeed . $salt);
                continue;
            }
            break;
        }
        return $candQuestion;
    }

    /**
     * ランダム生成した値で構造体を生成する
     */
    public static function random(string $seed): self
    {
        // シード値をuint32 の数値へ変換
        $seedInt = crc32($seed);
        // PHP標準のRNGにシードをセット（mt_srand は mt\_rand 系専用）
        mt_srand($seedInt);

        // 1〜9をシャッフルして3×3に
        $numbers = range(1, 9);
        shuffle($numbers);
        $matrix = array_chunk($numbers, 3); // [[1,2,3],[4,5,6],[7,8,9]]

        // Operator::cases() からランダム
        $ops = Operator::cases();

        // 縦3×横2
        $yokoFugo = [];
        for ($i = 0; $i < 3; $i++) {
            $row = [];
            for ($j = 0; $j < 2; $j++) {
                $row[] = $ops[array_rand($ops)];
            }
            $yokoFugo[] = $row;
        }

        // 縦2×横3
        $tateFugo = [];
        for ($i = 0; $i < 2; $i++) {
            $row = [];
            for ($j = 0; $j < 3; $j++) {
                $row[] = $ops[array_rand($ops)];
            }
            $tateFugo[] = $row;
        }

        // -----------------------
        // yokoKotae を計算
        // -----------------------
        $yokoKotae = [];
        for ($i = 0; $i < 3; $i++) {
            $a = $matrix[$i][0];
            $b = $matrix[$i][1];
            $c = $matrix[$i][2];

            // (a op1 b) op2 c
            $tmp = $yokoFugo[$i][0]->apply($a, $b);
            $result = $yokoFugo[$i][1]->apply($tmp, $c);

            $yokoKotae[] = $result;
        }

        // -----------------------
        // tateKotae を計算
        // -----------------------
        $tateKotae = [];
        for ($j = 0; $j < 3; $j++) {
            $a = $matrix[0][$j];
            $b = $matrix[1][$j];
            $c = $matrix[2][$j];

            // (a op1 b) op2 c
            $tmp = $tateFugo[0][$j]->apply($a, $b);
            $result = $tateFugo[1][$j]->apply($tmp, $c);

            $tateKotae[] = $result;
        }

        // -----------------------
        // 完成
        // -----------------------
        return new self(
            $matrix,
            $yokoFugo,
            $tateFugo,
            $yokoKotae,
            $tateKotae
        );
    }

    /**
     * 各フィールドの整合性チェック
     */
    private function validate(): void
    {
        // ===== matrix 3x3 =====
        if (count($this->matrix) !== 3) {
            throw new \InvalidArgumentException('matrix は 3 行必要です');
        }
        foreach ($this->matrix as $row) {
            if (!is_array($row) || count($row) !== 3) {
                throw new \InvalidArgumentException('matrix の各行は長さ 3 である必要があります');
            }
        }

        // ===== yokoFugo 3x2 =====
        if (count($this->yokoFugo) !== 3) {
            throw new \InvalidArgumentException('yokoFugo は 3 行必要です');
        }
        foreach ($this->yokoFugo as $row) {
            if (!is_array($row) || count($row) !== 2) {
                throw new \InvalidArgumentException('yokoFugo の各行は長さ 2 である必要があります');
            }
        }

        // ===== tateFugo 2x3 =====
        if (count($this->tateFugo) !== 2) {
            throw new \InvalidArgumentException('tateFugo は 2 行必要です');
        }
        foreach ($this->tateFugo as $row) {
            if (!is_array($row) || count($row) !== 3) {
                throw new \InvalidArgumentException('tateFugo の各行は長さ 3 である必要があります');
            }
        }

        // ===== yokoKotae 長さ3 =====
        if (count($this->yokoKotae) !== 3) {
            throw new \InvalidArgumentException('yokoKotae は長さ 3 である必要があります');
        }

        // ===== tateKotae 長さ3 =====
        if (count($this->tateKotae) !== 3) {
            throw new \InvalidArgumentException('tateKotae は長さ 3 である必要があります');
        }
    }

    public function toArray(): array
    {
        return [
            'matrix'         => $this->matrix,
            'yokoFugo' => array_map(
                fn($row) => array_map(fn($op) => $op->value, $row),
                $this->yokoFugo
            ),
            'tateFugo'    => array_map(
                fn($row) => array_map(fn($op) => $op->value, $row),
                $this->tateFugo
            ),
            'yokoKotae'   => $this->yokoKotae,
            'tateKotae'      => $this->tateKotae,
        ];
    }
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
    public function __toString(): string
    {
        $HALF_NUMS = "0 1 2 3 4 5 6 7 8 9";
        $FULL_NUMS = "０１２３４５６７８９";

        $lines = [];
        for ($y = 0; $y < 3; $y++) {
            $rowStr = '';
            for ($x = 0; $x < 3; $x++) {
                $cell = $this->matrix[$y][$x];
                if (count($cell) === 0) {
                    $rowStr .= '×';
                } elseif (count($cell) === 1) {
                    $numStr = (string)$cell[0];
                    $index = strpos($HALF_NUMS, $numStr);
                    if ($index !== false) {
                        $rowStr .= mb_substr($FULL_NUMS, intval($index / 2), 1);
                    } else {
                        $rowStr .= $numStr;
                    }
                } elseif (count($cell) === 2) {
                    $rowStr .= $cell[0] . $cell[1];
                } else {
                    $rowStr .= '　'; // 全角スペース
                }

                // 横の演算子
                if ($x < 2) {
                    $rowStr .= $this->yokoFugo[$y][$x]->symbol(); // Operator を文字列化
                }
            }

            // 横の答え
            $numStr = (string)$this->yokoKotae[$y];
            $index = strpos($HALF_NUMS, $numStr);
            if ($index !== false) {
                $rowStr .= '＝' . mb_substr($FULL_NUMS, intval($index / 2), 1);
            } else {
                $rowStr .= '＝' . $numStr;
            }

            $lines[] = $rowStr;

            // 縦の演算子（1行目・2行目だけ）
            if ($y < 2) {
                $tateStr = '';
                for ($x = 0; $x < 3; $x++) {
                    $tateStr .= $this->tateFugo[$y][$x]->symbol() . '　';
                }
                $lines[] = $tateStr;
            }
        }

        // 最下段の＝ と縦の答え
        $eqLine = '';
        for ($x = 0; $x < 3; $x++) {
            $eqLine .= '＝';
            if ($x < 2) {
                $eqLine .= '　';
            }
        }
        $lines[] = $eqLine;

        $tateAnsLine = '';
        for ($x = 0; $x < 3; $x++) {
            $numStr = (string)$this->tateKotae[$x];
            $index = strpos($HALF_NUMS, $numStr);
            if ($index !== false) {
                $tateAnsLine .= mb_substr($FULL_NUMS, intval($index / 2), 1);
            } else {
                $tateAnsLine .= $numStr;
            }
            if ($x < 2) {
                $tateAnsLine .= '　';
            }
        }
        $lines[] = $tateAnsLine;
        $lines[] = '';
        $lines[] = '';
        return implode(PHP_EOL, $lines);
    }
}

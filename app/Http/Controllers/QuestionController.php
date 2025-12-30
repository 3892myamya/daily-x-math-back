<?php

namespace App\Http\Controllers;

use App\Data\Field;
use App\Data\Operator;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class QuestionController extends Controller
{
    private function buildFieldData(): array
    {
        // 日替わり問題(Field構造体)を生成
        $seed = Carbon::now('Asia/Tokyo')->format('Y-m-d');
        // $seed = rand(); // debug
        $cacheKey = 'daily-cross-math-field:' . $seed;
        $expiresAt = Carbon::tomorrow('Asia/Tokyo')->startOfDay();
        return Cache::remember($cacheKey, $expiresAt, function () use ($seed) {
            $field = Field::generate($seed);
            $data = $field->toArray();
            $data['seed'] = $seed;
            return $data;
        });
    }

    public function getQuestion()
    {
        $data = $this->buildFieldData();
        unset($data['matrix']);
        return response()->json($data);
    }

    public function getAnswer()
    {
        $data = $this->buildFieldData();
        unset(
            $data['seed'],
            $data['yokoFugo'],
            $data['tateFugo'],
            $data['yokoKotae'],
            $data['tateKotae']
        );
        return response()->json($data);
    }

    public function solveTest()
    {
        $yokoFugo = [
            [Operator::SUB, Operator::MUL],
            [Operator::ADD, Operator::DIV],
            [Operator::MUL, Operator::SUB],
        ];
        $tateFugo = [
            [Operator::SUB, Operator::SUB, Operator::ADD],
            [Operator::SUB, Operator::ADD, Operator::DIV],
        ];
        $yokoKotae = [14, 2, 4];
        $tateKotae = [5, 11, 2];
        // 仮問題
        $field = Field::withEmptyMatrix(
            $yokoFugo,
            $tateFugo,
            $yokoKotae,
            $tateKotae
        );
        $field->questionSolve();
        echo $field;
        //８－６×７＝14
        //－　－　＋　
        //２＋４÷３＝２
        //－　＋　÷　
        //１×９－５＝４
        //＝　＝　＝
        //５　11　２
        // JSON で返す（Laravelのresponse()->jsonはtoArrayに対応）
        return response()->json($field->toArray());
    }
}

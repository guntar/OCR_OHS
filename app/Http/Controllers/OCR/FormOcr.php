<?php

namespace OCR_OHS\Http\Controllers\OCR;

use OCR_OHS\Http\Controllers\Controller;
use Illuminate\Http\Request;
//use OCR_OHS\OCRForm;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;

class FormOcr extends Controller
{
    public function show(Request $request)
    {
        return view('form_ocr');
    }

    public function analyze(Request $request){
        if($request->hasFile('file_to_analyse')) {

            $file = $request->file('file_to_analyse');
            Storage::disk('public')->put('uploads/' . $file->getClientOriginalName(), file_get_contents($file));
            $contents = public_path('storage/uploads/' . $file->getClientOriginalName());


            $client = new Client();
            $body = file_get_contents($contents, true);
            $mime = $file->getClientMimeType();
            $type = $request->get("select");
            if ($type == "prebuilt") {
                $response = $client->post(env('OCP_RESOURCE_HOST_AZURE_ANALYZE_INVOICE'), array(
                    'headers' => [
                        'Content-type' => $mime,
                        'Ocp-Apim-Subscription-Key' => env('OCP_APIM_SUBSCRIPTION'),
                    ],
                    'body' => $body
                ));
            } else {
                $response = $client->post(env('OCP_RESOURCE_HOST_AZURE_ANALYZE_LAYOUT'), array(
                    'headers' => [
                        'Content-type' => $mime,
                        'Ocp-Apim-Subscription-Key' => env('OCP_APIM_SUBSCRIPTION'),
                    ],
                    'body' => $body
                ));
            }

            $message = $response->getHeaderLine('Operation-Location');
            $messages['header'] = $message;
            $messages['mime'] = $mime;
            $messages['type'] = $type;
            $resultid = substr($message, strpos($message, "analyzeResults/") + 15);
            $messages['resultid'] = $resultid;

            sleep(10);

            $result = $this->getResult($resultid, $type);

            $messages['result'] = $result['words'];
            $messages['json'] = $result['json'];
            $messages['file'] = $file->getClientOriginalName();
        }
        else{
            $type = $request->get("select");

            $client = new Client();
            if($type == "train"){
                $body = [
                    'source' => env('OCP_SAS_URI')
                ];
                $data = json_encode($body, JSON_FORCE_OBJECT);
                $response = $client->post(env('HOST_AZURE_TRAINING_MODEL'), array(
                    'headers' => [
                        'Content-type' => 'application/json',
                        'Ocp-Apim-Subscription-Key' => env('OCP_APIM_SUBSCRIPTION'),
                    ],
                    'body' => $data
                ));
                $message = $response->getHeaderLine('Location');
                $messages['header'] = $message;
                $resultid = substr($message, strpos($message, "models/") + 7);
                sleep(15);
                $result = $this->getResultTrainModel($resultid);

                $messages['resultid'] = $resultid;
                $messages['type'] = $type;
                $messages['result'] = $result['words'];
                $messages['json'] = $result['json'];
            }
        }
        return redirect('/')->with('status', $messages);
    }

    public function getResult($resultid, $type){
        $client = new Client();
        if($type == "prebuilt") {
            $response = $client->get(env('OCP_RESOURCE_HOST_AZURE_GET_RESULT_INVOICE') . $resultid, array(
                'headers' => [
                    'Content-type' => 'application/json',
                    'Ocp-Apim-Subscription-Key' => env('OCP_APIM_SUBSCRIPTION'),
                ],
            ));
        }else{
            $response = $client->get(env('OCP_RESOURCE_HOST_AZURE_GET_RESULT_LAYOUT') . $resultid, array(
                'headers' => [
                    'Content-type' => 'application/json',
                    'Ocp-Apim-Subscription-Key' => env('OCP_APIM_SUBSCRIPTION'),
                ],
            ));
        }

        $output = json_decode($response->getBody()->getContents(), true);

        if($type == "prebuilt"){
            $result ['words'] = $this->readPrebuiltInvoice($output);
            $result ['json'] = json_encode($output, JSON_PRETTY_PRINT);
            return $result;
        }

        $resultOCR = $output["analyzeResult"]["readResults"][0]["lines"];
        $items = $resultOCR;
        $words = [];
        $i = 0;
        foreach ($items as $results){
            $words[$i]['lines_text']  = $results['text'];
            $words_lines = $results["words"];
            $word = [];
            $j = 0;
            foreach ( $words_lines as $word_item){
                $word[$j]['text']  = $word_item['text'];
                $word[$j]['confidence']  = $word_item['confidence'];
                $j++;
            }
            $words[$i]["breakdown_word"] = $word;
            $i++;
        }
        $result ['words'] = $words;
        $result ['json'] = json_encode($output, JSON_PRETTY_PRINT);
        return $result;
    }

    private function readPrebuiltInvoice($output){
        $resultOCR = $output["analyzeResult"]["documentResults"];
        $words = [];
        $i = 0;
        foreach ($resultOCR[0]["fields"] as $key => $results){
            $words[$i]['key'] = $key;
            $words[$i]['text'] = $results['text'];
            $words[$i]['confidence'] = $results['confidence'];
            $i++;
        }
        return $words;
    }

    public function getResultTrainModel($resultid){
        $client = new Client();
        $response = $client->get(env('HOST_AZURE_TRAINING_MODEL_RESULT'). $resultid, array(
            'headers' => [
                'Content-type' => 'application/json',
                'Ocp-Apim-Subscription-Key' => env('OCP_APIM_SUBSCRIPTION'),
            ],
        ));
        $output = json_decode($response->getBody()->getContents(), true);

        $result ['words'] = $output;
        $result ['json'] = json_encode($output, JSON_PRETTY_PRINT);
        return $result;
    }
}
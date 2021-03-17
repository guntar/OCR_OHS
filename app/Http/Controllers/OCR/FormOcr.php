<?php

namespace OCR_OHS\Http\Controllers\OCR;

use OCR_OHS\Http\Controllers\Controller;
use Illuminate\Http\Request;
//use OCR_OHS\OCRForm;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;

class FormOcr extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\View\View
     */
    public function show(Request $request)
    {
        return view('form_ocr');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function analyze(Request $request){
        // Checking file request
        if($request->hasFile('file_to_analyse')) {

            $file = $request->file('file_to_analyse');
            // storing file using storage function
            Storage::disk('public')->put('uploads/' . $file->getClientOriginalName(), file_get_contents($file));
            //assign file url to $contents variable
            $contents = public_path('storage/uploads/' . $file->getClientOriginalName());
            // Create Client Guzzle
            $client = new Client();
            // Set Body as binary file not json source url/streaming url file
            $body = file_get_contents($contents, true);
            $mime = $file->getClientMimeType();
            $type = $request->get("select");

            if ($type == "prebuilt") {
                // Guzzle Client to post invoice prebuilt
                $response = $client->post(env('OCP_RESOURCE_HOST_AZURE_ANALYZE_INVOICE'), array(
                    'headers' => [
                        'Content-type' => $mime,
                        'Ocp-Apim-Subscription-Key' => env('OCP_APIM_SUBSCRIPTION'),
                    ],
                    'body' => $body
                ));
            } else {
                // Guzzle Client to post analyze layout
                $response = $client->post(env('OCP_RESOURCE_HOST_AZURE_ANALYZE_LAYOUT'), array(
                    'headers' => [
                        'Content-type' => $mime,
                        'Ocp-Apim-Subscription-Key' => env('OCP_APIM_SUBSCRIPTION'),
                    ],
                    'body' => $body
                ));
            }

            // Extract Operation-Location Header to getting resultid
            $message = $response->getHeaderLine('Operation-Location');
            $messages['header'] = $message;
            $messages['mime'] = $mime;
            $messages['type'] = $type;
            // get result id after analyzeResults/ uri word
            $resultid = substr($message, strpos($message, "analyzeResults/") + 15);
            $messages['resultid'] = $resultid;

            sleep(10);

            // Getting result of layout analyze
            $result = $this->getResult($resultid, $type);

            // identify variable response for view
            $messages['result'] = $result['words'];
            $messages['json'] = $result['json'];
            $messages['file'] = $file->getClientOriginalName();
        }
        else{
            // for traning model
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
                // get Location from Header Response
                $message = $response->getHeaderLine('Location');
                $messages['header'] = $message;
                // extract model_id from Location Header after models/ uri word
                $resultid = substr($message, strpos($message, "models/") + 7);
                // add sleep for waiting getting result response training model
                sleep(15);
                // getting result model training
                $result = $this->getResultTrainModel($resultid);
                // identify variable response for view
                $messages['resultid'] = $resultid;
                $messages['type'] = $type;
                $messages['result'] = $result['words'];
                $messages['json'] = $result['json'];
            }
        }
        // return redirect with output response
        return redirect('/')->with('status', $messages);
    }

    /**
     * Function to get result for layout or prebuilt invoice
     * @param $resultid
     * @param $type
     * @return mixed
     */
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
        // if prebuilt type
        if($type == "prebuilt"){
            $result ['words'] = $this->readPrebuiltInvoice($output);
            $result ['json'] = json_encode($output, JSON_PRETTY_PRINT);
            return $result;
        }
        // extract line text from json output
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

    /**
     * Editing result from prebuilt invoice
     * @param $output
     * @return array
     */
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

    /**
     * Getting Result for Train Model Without Labelling
     * @param $resultid
     * @return mixed
     */
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
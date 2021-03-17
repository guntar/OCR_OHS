<!DOCTYPE html>
<html>
<head>
    <title>Simplus Form OCR Laravel</title>

    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Crimson+Pro&family=Literata">
    <style>
        body {
            margin: 0;
            padding: 10px;
            width: 100%;
            font-family: Segoe UI,SegoeUI,Helvetica Neue,Helvetica,Arial,sans-serif;
            font-size: 48px;
        }
        div:nth-child(1) {
            font-family: 'Crimson Pro', serif;
            font-size: 18px;
        }
        div:nth-child(2) {
            font-family: 'Literata’, serif;
        }
        fieldset > legend {
            text-align: left;
            font-size: xx-large;
        }
    </style>

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>
<body>
<div class="container">
    <div class="content">
        <div class="jumbotron">
            <h1>Form OCR </h1>
            <p>Using Azure OCR Cognitive Service with Laravel 5.2.45</p>
            <p><a class="btn btn-info btn-lg" href="https://docs.microsoft.com/en-us/azure/cognitive-services/form-recognizer/?branch=release-build-cogserv-forms-recognizer" role="button" target="_blank">Learn more</a></p>
        </div>
        <div class="box">
            {!! Form::open(['url' => '/getResultOcr', 'method' => 'post','enctype' => "multipart/form-data", 'class' => 'form-horizontal']) !!}
            <fieldset>
                <legend>Form Recognizer using REST API</legend>
                <!-- Upload File and run layout -->

                <!-- Select Form Type -->
                <div class="form-group">
                    {!! Form::label('select', 'Type', ['class' => 'col-lg-2 control-label'] )  !!}
                    <div class="col-lg-4">
                        {!!  Form::select('select', ['analyze' => 'Analyze Layout', 'prebuilt' => 'Prebuilt Invoice','train' => 'Train Model'],  'analyze', ['class' => 'form-control' ]) !!}
                    </div>
                </div>

                <div class="form-group">
                    {!! Form::label('file_upload', 'Local File Source:', ['class' => 'col-lg-2 control-label']) !!}
                    <div class="col-lg-5">
                        {!! Form::file('file_to_analyse', ['class' => 'form-control', 'placeholder' => 'File Source']) !!}
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="form-group">
                    <div class="col-lg-10 col-lg-offset-2">
                        {!! Form::submit('Run Analysis ', ['class' => 'btn btn-lg btn-primary pull-left'] ) !!}
                    </div>
                </div>
            </fieldset>

            {!! Form::close()  !!}
        </div>
        <div class="section">
            @if (session('status'))
                @if(session('status')['type'] !== "train")
                <div class="col-md-6">
                    <br>
                    <embed src="{{ asset('storage/uploads/'.session('status')['file']) }}" style="object-fit: cover;" height="750" width="550" type="{{ session('status')['mime'] }}" role="presentation" alt="">
                </div>
                @endif
            <div class="result col-md-6">
                <ul class="nav nav-tabs" id="ocrTab" role="tablist">
                    <li class="nav-item active">
                        <a class="nav-link active" id="result-tab" data-toggle="tab" href="#result" role="tab" aria-controls="home" aria-selected="true">Result</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="json-tab" data-toggle="tab" href="#json" role="tab" aria-controls="json" aria-selected="false">JSON</a>
                    </li>
                </ul>
                <div class="tab-content" id="ocrTabContent">
                    <div class="tab-pane fade active in" id="result" role="tabpanel" aria-labelledby="result-tab">
                        <p> <strong> Operation-Location : </strong> </p>
                            <code>{{ session('status')['header'] }}</code>
                        <br>
                        <p> <strong> Result-ID : </strong></p>
                            <code>{{ session('status')['resultid'] }} </code>
                        <br>
                        <p> <strong>OCR Text Result : </strong></p>
                        @if (session('status')['type'] == "prebuilt")
                            <ol>
                                <?php
                                    $result = session('status')['result'];
                                    foreach ($result as $results){
                                        echo "<li>";
                                        echo "<span class='label label-info'>";
                                        echo '<strong>';
                                        echo $results['key'];
                                        echo '</strong>';
                                        echo "</span>";
                                        echo '<br>';
                                        echo "<span class='label label-warning'>";
                                        echo $results['text']." -> ".($results['confidence']*(100))." %";
                                        echo '</span>';
                                        echo "</li>";
                                    }
                                ?>
                            </ol>
                        @elseif(session('status')['type'] == "analyze")
                            <ol>
                                <?php
                                $result = session('status')['result'];
                                foreach ($result as $results){
                                    echo "<li>";
                                    echo "<span class='label label-warning'>";
                                    echo $results['lines_text'];
                                    echo '</span>';
                                    echo "</li>";
                                }
                                ?>
                            </ol>
                        @elseif(session('status')['type'] == "train")
                            <ol>
                                <?php
                                $result = session('status')['result'];
                                foreach ($result as $results){
                                    echo "<li>";
                                    echo "<span class='label label-warning'>";
                                    echo '<pre>';
                                    print_r($results);
                                    echo '</pre>';
                                    echo '</span>';
                                    echo "</li>";
                                }
                                ?>
                            </ol>
                        @endif
                    </div>
                    <div class="tab-pane fade" id="json" role="tabpanel" aria-labelledby="json-tab">
                        <code>
                            <pre>
                            {{ session('status')['json'] }}
                            </pre>
                        </code>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<!-- Latest compiled and minified JavaScript -->
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
<script type="application/javascript">

</script>
</body>
</html>

@extends ('admin.layouts.app')
@section ('content')
    <div class="container">
        <div class="row">
            <div class="col-md-2"></div>
            <div class="col-md-8">
                <div class="d-flex justify-content-between mb20">
                    <h1 class="title-bar">{{__('System Update')}}</h1>
                </div>

                <div class="panel @if($ready_for_update) d-none @endif" id="license_key_form">
                    <div class="panel-title"><strong>{{__('Version: 2.4')}}</strong></div>
                    <div class="panel-body">
                        <div class="alert alert-info">
                            {{__("Currently you are using the top version, I will let you know if there is any new version.")}}
                        </div>
                        <h4>News and updates</h4>
                        <a class="twitter-timeline" href="https://twitter.com/WpDesignerBuddy?ref_src=twsrc%5Etfw"></a> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
                        <ul>
                            <li><a></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@props(['from' => null, 'to' => null, 'colClass' => 'col-md-2'])

<div class="{{ $colClass }}">
    <label for="from" class="form-label">From</label>
    <input type="date" id="from" name="from" class="form-control" value="{{ $from }}">
</div>
<div class="{{ $colClass }}">
    <label for="to" class="form-label">To</label>
    <input type="date" id="to" name="to" class="form-control" value="{{ $to }}">
</div>
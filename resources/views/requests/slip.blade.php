@extends('layouts.app')
@section('title', 'Excuse & Admission Slip')

@section('content')
<div class="no-print slip-actions">
    <a href="{{ route('requests.show', $item) }}">← Back</a>
    <div class="slip-print-action">
        <span>* Print in A4 size</span>
        <button type="button" onclick="window.print()">Print / Save PDF</button>
    </div>
</div>

<article class="excuse-slip">
    <header class="slip-header">
        <img src="{{ asset('images.jpg') }}" alt="OLSHCO logo" class="slip-logo">
        <div class="slip-heading">
            <p>OUR LADY OF THE SACRED HEART COLLEGE OF GUIMBA, INC.</p>
            <p>COLLEGE DEPARTMENT</p>
            <h1>BS Information Technology</h1>
            <h2>EXCUSE &amp; ADMISSION SLIP</h2>
        </div>
    </header>

    <section class="slip-body">
        <div class="form-row date-row">
            <span>DATE:</span>
            <span class="fill-line">{{ $item->approved_at?->format('F d, Y') }}</span>
        </div>

        <div class="recipient">
            <div class="form-row">
                <span>To:</span>
                <span class="fill-line">{{ $item->facilitator->display_name }}</span>
            </div>
            <em>Course Facilitator</em>
        </div>

        <p>Dear <strong>Sir/Madam:</strong></p>

        <p class="sentence">
            I was absent from your class due to
            <span class="fill-line reason-line">{{ $item->reasonCategory->name }}</span>.
        </p>

        <p class="sentence request-line">
            In this connection, please excuse me for failing to attend your class
            <span class="fill-line class-line">{{ $item->subject->code }}</span>
            last
            <span class="fill-line absence-line">{{ $item->absence_date->format('F d, Y') }}</span>.
        </p>

        <p>Thank you very much for your consideration.</p>

        <div class="respectfully">
            <p>Respectfully,</p>
            <div class="student-signature">
                <strong>{{ $item->student->user->name }}</strong>
            </div>
        </div>
    </section>

    <table class="approval-table">
        <colgroup>
            <col class="remarks-column">
            <col class="issuer-column">
            <col class="date-column">
        </colgroup>
        <thead>
            <tr>
                <td>Remarks</td>
                <td>Issued and Verified by</td>
                <td>Date:</td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="remarks-cell">
                    <strong class="slip-result">{{ $item->slip_remark ?? 'EXCUSED' }}</strong>
                    @if($item->official_remarks)
                        <span class="official-remarks">{{ $item->official_remarks }}</span>
                    @endif
                </td>
                <td class="issuer">
                    <strong>{{ strtoupper($programHeadName) }}</strong>
                    <em>BSIT PROGRAM HEAD</em>
                </td>
                <td class="verified-date">{{ $item->approved_at?->format('m/d/Y') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="slip-verification">
        <img src="{{ $qrCode }}" alt="QR code for slip verification">
        <div>
            <strong>SCAN TO VERIFY</strong>
            <span>{{ $item->reference_number }}</span>
        </div>
    </div>
</article>

<style>
    .slip-actions {
        width: min(210mm, 100%);
        margin: 0 auto 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .slip-actions a,
    .slip-actions button {
        border: 1px solid #cbd5e1;
        border-radius: .65rem;
        background: #fff;
        padding: .6rem 1rem;
        font: 600 .875rem/1 sans-serif;
        color: #1e293b;
        cursor: pointer;
    }
    .slip-actions button {
        border-color: #123a63;
        background: #123a63;
        color: #fff;
    }
    .slip-print-action {
        display: flex;
        align-items: center;
        gap: .75rem;
    }
    .slip-print-action span {
        color: #b3262e;
        font: 600 .8rem/1.2 Arial, Helvetica, sans-serif;
    }
    .excuse-slip {
        box-sizing: border-box;
        position: relative;
        width: 210mm;
        min-height: 148.5mm;
        margin: 0 auto;
        padding: .36in .48in .32in;
        background: #fff;
        color: #000;
        box-shadow: 0 12px 32px rgb(15 23 42 / 16%);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 14px;
        line-height: 1.3;
    }
    .excuse-slip + .excuse-slip {
        margin-top: 1rem;
    }
    .slip-header {
        position: relative;
        min-height: 1.38in;
        display: flex;
        align-items: flex-start;
        justify-content: center;
    }
    .slip-logo {
        position: absolute;
        top: 0;
        left: 0;
        width: .95in;
        height: .95in;
        object-fit: contain;
    }
    .slip-heading {
        width: 100%;
        padding-top: .28in;
        text-align: center;
        font-family: "Arial Narrow", Arial, Helvetica, sans-serif;
        font-weight: 700;
        line-height: 1.12;
    }
    .slip-heading p,
    .slip-heading h1,
    .slip-heading h2 {
        margin: 0;
    }
    .slip-heading h1 {
        font-size: 19px;
        font-stretch: condensed;
    }
    .slip-heading h2 {
        margin-top: .18in;
        font-size: 16px;
    }
    .slip-body {
        margin-top: .03in;
    }
    .slip-body p {
        margin: 0 0 .18in;
    }
    .form-row {
        display: flex;
        align-items: flex-end;
        gap: 6px;
    }
    .fill-line {
        display: inline-block;
        min-height: 18px;
        border-bottom: 1px solid #000;
        padding: 0 5px 1px;
        text-align: center;
        font-weight: 600;
        white-space: nowrap;
    }
    .date-row {
        margin-bottom: .18in;
    }
    .date-row .fill-line {
        width: 1.95in;
    }
    .recipient {
        width: 2.55in;
        margin-bottom: .19in;
    }
    .recipient .form-row .fill-line {
        flex: 1;
    }
    .recipient em {
        display: block;
        margin-left: .27in;
        font-size: 13px;
        line-height: 1.1;
    }
    .sentence {
        display: flex;
        align-items: flex-end;
        gap: 4px;
        white-space: nowrap;
    }
    .reason-line {
        flex: 1;
    }
    .request-line {
        gap: 5px;
    }
    .class-line {
        width: 2.1in;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .absence-line {
        width: 1.75in;
    }
    .respectfully {
        margin-top: .34in;
    }
    .student-signature {
        width: 1.7in;
        min-height: .29in;
        margin-top: .3in;
        padding: 0 4px 2px;
        border-bottom: 1px solid #000;
        text-align: center;
        font-size: 12px;
    }
    .approval-table {
        width: 100%;
        margin-top: .2in;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 13px;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .approval-table td {
        border: 1px solid #000;
        padding: 1px 8px;
    }
    .approval-table thead td {
        height: 18px;
    }
    .approval-table tbody td {
        height: .55in;
        vertical-align: middle;
    }
    .remarks-column { width: 44%; }
    .issuer-column { width: 35%; }
    .date-column { width: 21%; }
    .approval-table .remarks-cell {
        overflow-wrap: anywhere;
    }
    .approval-table .slip-result {
        display: block;
        color: red;
        font-size: 16px;
        font-weight: 700;
    }
    .approval-table .official-remarks {
        display: block;
        margin-top: 3px;
        color: #000;
        font-size: 11px;
        font-weight: 400;
        line-height: 1.15;
    }
    .approval-table .issuer {
        text-align: center;
        font-size: 11px;
    }
    .approval-table .issuer strong,
    .approval-table .issuer em {
        display: block;
    }
    .approval-table .verified-date {
        text-align: center;
    }
    .slip-verification {
        position: absolute;
        right: .48in;
        top: .36in;
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 9px;
        line-height: 1.15;
    }
    .slip-verification img {
        width: .68in;
        height: .68in;
    }
    .slip-verification strong,
    .slip-verification span {
        display: block;
    }
    .slip-verification span {
        margin-top: 2px;
        color: #475569;
    }
    @page {
        size: A4 portrait;
        margin: 0;
    }
    @media print {
        .excuse-slip {
            width: 210mm !important;
            min-height: 148.5mm !important;
            height: 148.5mm !important;
            margin: 0 !important;
            padding: 7mm 10mm 6mm !important;
            box-shadow: none !important;
            font-size: 11px !important;
            line-height: 1.2 !important;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .slip-header {
            min-height: 27mm !important;
        }
        .slip-logo {
            width: 20mm !important;
            height: 20mm !important;
        }
        .slip-heading {
            padding-top: 5mm !important;
        }
        .slip-heading h1 {
            font-size: 16px !important;
        }
        .slip-heading h2 {
            margin-top: 3mm !important;
            font-size: 13px !important;
        }
        .slip-body p {
            margin-bottom: 3mm !important;
        }
        .date-row,
        .recipient {
            margin-bottom: 3mm !important;
        }
        .respectfully {
            margin-top: 5mm !important;
        }
        .student-signature {
            margin-top: 4mm !important;
            min-height: 5mm !important;
        }
        .approval-table {
            margin-top: 4mm !important;
            font-size: 10px !important;
        }
        .approval-table tbody td {
            height: 10mm !important;
        }
        .approval-table .slip-result {
            font-size: 13px !important;
        }
        .approval-table .official-remarks {
            margin-top: 1mm !important;
            font-size: 8px !important;
        }
        .approval-table .issuer {
            font-size: 9px !important;
        }
        .slip-verification {
            right: 10mm !important;
            top: 7mm !important;
            bottom: auto !important;
            font-size: 7px !important;
        }
        .slip-verification img {
            width: 17mm !important;
            height: 17mm !important;
        }
        .excuse-slip + .excuse-slip {
            border-top: 1px dashed #777;
        }
    }
    @media screen and (max-width: 1000px) {
        .excuse-slip {
            width: 100%;
            min-height: auto;
        }
        .sentence {
            white-space: normal;
            flex-wrap: wrap;
        }
        .reason-line {
            min-width: 12rem;
        }
    }
</style>
@endsection

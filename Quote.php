<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Quote_manage extends CI_Controller {

    public function __construct() {
        parent::__construct();
        if (!$this->session->userdata("login_type")) {
            redirect('/login');
        }
        $this->load->model('company/Quote_model');
        $this->load->model('customer/Customer_quote_model');
        $this->load->model('admin/email_model');
        $this->load->model('customer/customer_calendar_model');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        $this->item_unit_master = set_table_prefix() . "item_unit_master";
        $logged_in_user_id = $this->session->userdata();
        //  pr($logged_in_user_id);
    }

    public function index() {

    }

    public function all_quotes() {
        $this->data['header_type'] = '4';
        $this->data['content'] = 'customer/manage_quotes';
        $this->data['body_class'] = 'company';
        render_page('template', $this->data);
    }

    public function quotes_lists($type = "") {

        $serchable_quote_id = (@$this->input->get('quote_search')) ? $this->input->get('quote_search') : "";
        $sort = (@$this->input->get('order')) ? $this->input->get('order') : "";

        $data = array();
        $page = $this->input->get('draw'); //page listing
        $start = $this->input->get('start');
        $limit = $this->input->get('length');
        $search = $this->input->get('search'); //to get search value
        $orderval = $this->input->get('order'); //to get value for sort column details
        //For Sorting
        $columns = array(
            // datatable column index  => database column name
            0 => '',
            1 => '',
            2 => '',
            3 => '',
            4 => '',
            5 => '',
            6 => '',
            7 => ''
        );
        $order_col = $columns[$orderval[0]['column']];
        $orderby = $orderval[0]['dir'];

        $quote_list = $this->Customer_quote_model->getQuoteList($type, $serchable_quote_id, $orderval, $start, $limit);

        $recordsFiltered = count($quote_list['data']);
        $total_record = $quote_list['t_r'];

        foreach ($quote_list['data'] as $quote) {
            // check for last update
            $sql1 = "select front_user_id from quote_status where quote_id = " . @$quote->quote_id . " and status_id = 10 ORDER BY quote_status_id DESC LIMIT 1";
            $res1 = $this->db->query($sql1)->row();
            if ($res1) {
                $sql2 = "select first_name, last_name from company_staff_master where front_user_id = '" . @$res1->front_user_id . "'";
                $res2 = $this->db->query($sql2)->row();
                $last_update = $res2->first_name . " " . $res2->last_name . " | " . date("d.m.Y H:i A", strtotime(@$quote->modified_on));
            } else {
                $last_update = $quote->first_name . " " . $quote->last_name . " | " . date("d.m.Y H:i A", strtotime(@$quote->modified_on));
            }

            if ($quote) {
                $quote_status_details = quote_status_details($quote->status_id);
                $quote_status = $quote_status_details['status_class'];
            }


            $this->db->select('COUNT(mm.message_id) as quote_msg_count');
            $this->db->from('messages_master as mm');
            $this->db->join('message_receiver_list as mrl', 'mm.message_id = mrl.message_id');
            $this->db->where('mm.quote_id', @$quote->quote_id);
            $this->db->where('mrl.receiver_id', $this->session->userdata('id'));
            $this->db->where('mrl.is_read', '0');
            $message_count_query = $this->db->get();
            $message_count_query_result = $message_count_query->row();

            if ($message_count_query_result->quote_msg_count > 0) {
                $message_count = '<div class="msg"><a href="' . base_url() . 'customer/message/loadmessagedetail/' . encrypt_string($quote->quote_id) . '"><i class="fa fa-envelope"></i><span class="count">' . $message_count_query_result->quote_msg_count . '</span></a></div>';
            } else {
                $message_count = '<div class="msg"><a href="' . base_url() . 'customer/message/loadmessagedetail/' . encrypt_string($quote->quote_id) . '"><i class="fa fa-envelope"></i></a></div>';
            }

            $this->db->select('mm.message');
            $this->db->from('messages_master as mm');
            $this->db->join('message_receiver_list as mrl', 'mm.message_id = mrl.message_id');
            $this->db->where('mm.quote_id', @$quote->quote_id);
            $this->db->where('mrl.receiver_id', $this->session->userdata('id'));
            $this->db->where('mrl.is_read', '0');
            $this->db->order_by('mrl.created_on', 'desc');
            $message_query = $this->db->get();
            $message_query_result = $message_query->result();


            if (empty($message_query_result)) {
                $message = 'No new message';
            } else {
                $message = $message_query_result[0]->message;
            }
            ////////////////
            // quote_id column

            $user_id = $this->session->userdata('id');
            $sql = "SELECT `quote_id` FROM quote_status WHERE `quote_id` != 0 AND `front_user_id` = $user_id AND `customer_notifications_flag` = '0' GROUP BY quote_id";
            $result1 = $this->db->query($sql)->result();
            //pr($result1);
            $quote_result = array();
            foreach ($result1 as $key) {
                $quote_result[] = $key->quote_id;
            }
            if (in_array($quote->quote_id, $quote_result)) {
                $quote_id_column = '<a class="msg" style="width: 100%;float: left;cursor:pointer;" href="' . base_url() . 'customer/quote_details/' . md5(@$quote->quote_id) . '"><span class="count" style="top: 0px; left: -3px;">!</span>' . quoteToCompanyQuoteId(@$quote->quote_id) . '</a>';
            } else {
                $quote_id_column = '<a style="width: 100%;float: left;cursor:pointer;" href="' . base_url() . 'customer/quote_details/' . md5(@$quote->quote_id) . '">' . quoteToCompanyQuoteId(@$quote->quote_id) . '</a>';
            }
            //////////////////
            // customer name
            ////////////////
            $company_data = $this->Quote_model->get_data("company_master", array("company_id" => @$quote->company_id));
            $company_name = $company_data[0]->company_name;


            $data[] = array($quote_id_column, date("d.m.Y H:i A", strtotime(@$quote->created_on)), @$last_update, $company_name, @$quote->company_id, @$quote->total, $message_count, $quote_status);
        }
        $output = array(
            "draw" => $page, /* Page o/p */
            "recordsTotal" => intval($recordsFiltered), /* Total records in table */
            "recordsFiltered" => intval($total_record), /* filter datatables */
            "data" => $data,
        );
        //send json for display output
        echo json_encode($output);
    }

    public function get_companies_email_ids() {
        $search_value = $this->input->post('search_value');
        $email_ids = $this->Customer_quote_model->getAllCompaniesEmailIds($search_value);

        if (!empty($email_ids)) {
            echo json_encode($email_ids);
        } else {
            echo json_encode($this->lang->line('no_records_found'));
        }
    }

    public function ask_for_quote() {
        $post_data = $this->input->post();
        unset($post_data['submit']);

        $request_token = $this->Customer_quote_model->requestQuote($post_data);
        if ($request_token == '') {
            $query = $this->db->get_where("email_template_master", array('email_type' => "Company Registration For Accepting Quote Request"))->row();
            $html = $query->email_body;
            $from = admin_email();
            $to = $this->input->post('company_email_id');
            $subject = $query->email_subject;
            $front = str_replace('{lastname}', $this->session->userdata('last_name'), $html);
            $front = str_replace('{firstname}', $this->session->userdata('first_name'), $front);
            $base = base_url();
            $token = encrypt_string($request_token);
            $front = str_replace('{baseurl}', $base, $front);
            $front = str_replace('{token}', $token, $front);
            $body = $front;
            $this->email_model->sendMail($from, $to, $cc = "", $bcc = "", $subject, $body, $attachment = "");
        }
        $this->session->set_flashdata('success', $this->lang->line('quote_request_message'));
        redirect('customer/quotes');
    }

// quote details page
    //////////////////
    public function quote_details() {
        $quoteId = $this->uri->segment(3);
        $version_value = $version_value = @$this->input->get('v');
        $res = $this->Quote_model->get_data("quote_master", array("md5(quote_id)" => $quoteId));
        $logged_in_user_id = $this->session->userdata('id');
        $this->Customer_quote_model->read_quote_changes($res[0]->quote_id, $logged_in_user_id);

        $company_staff_details = $this->Customer_quote_model->get_data("company_staff_master", array("company_staff_id" => $res[0]->company_staff_id));
        $lang = $this->session->userdata('site_lang');
        $version_array = $this->Quote_model->getVersionArray($res[0]->quote_id);
        $latest_version = $this->Quote_model->getLatestVersion($res[0]->quote_id);

        $this->data['quote_id'] = $res[0]->quote_id;
        $this->data['quote_details'] = $res[0];
        $this->data['company_staff_details'] = $company_staff_details;
        $this->data['versionArray'] = $version_array;
        if ($version_value == '') {
            $version_number = $latest_version;
        } else {
            $version_number = substr($version_value, 2);
        }

        $pdf_path = quote_preview($res[0]->quote_id, $version_number, $lang);

        //quote directory
        $company_id = $company_staff_details->company_id;
        $company_dir = "company" . $company_id;
        $dirpath = "uploads/front/company/" . $company_dir;
        $this->data['dir_path'] = $dirpath;
        $this->data['quote_pdf_path'] = $pdf_path;
        $this->data['version_number'] = $version_number;
        $this->data['latest_version'] = $latest_version;

        $this->data['change_request_id'] = $this->Customer_quote_model->change_request_in_quote($res[0]->quote_id, $res[0]->customer_id);
        $this->data['question_topic_id'] = $this->Customer_quote_model->general_message_id($res[0]->quote_id, $res[0]->customer_id);
        $this->data['currency'] = $this->Customer_quote_model->get_data('quote_currency_master', array('q_currency_id' => $res[0]->currency_id));
        $this->data['status_id'] = $res[0]->status_id;
        $this->data['company_quote_id'] = quoteToCompanyQuoteId($res[0]->quote_id);
        $this->data['lang'] = $lang;

        $this->data['header_type'] = '4';
        $this->data['content'] = 'customer/quote_details';
        $this->data['body_class'] = 'customers_quotes_ungoing_and_change';

        $this->session->set_userdata('quote_id', $res[0]->quote_id);
        $page_content['quote_id'] = $res[0]->quote_id;
        $this->data['calender_html'] = $this->load->view('calendar/customer_calendar', $page_content, true);

        render_page('template', $this->data);
    }

    function strReplaceAssoc(array $replace, $subject) {
        return str_replace(array_keys($replace), array_values($replace), $subject);
    }

    public function reject_quote() {
        $post_data = $this->input->post();
        extract($post_data);
        $quote_id = decrypt_string($quote_id);
        $res = $this->Customer_quote_model->set_status($quote_id, '3', '');
        $res_status = $this->Customer_quote_model->set_quote_status($quote_id, '3');
        if ($res_status > 0) {
            $data = array(
                "status" => "success",
                "msg" => "Quote rejected."
            );
        } else {
            $data = array(
                "status" => "fail",
                "msg" => "Something went wrong."
            );
        }
        echo json_encode($data);
    }

    public function reject_quote_comments() {
        $post_data = $this->input->post();

        extract($post_data);
        $quote_id = decrypt_string($quote_id);
        $quote_reject_comment = $post_data['quote_reject_comment'];
        $quote_details = $this->Quote_model->getQuoteDetails(md5($quote_id));
        $logged_in_user_id = $this->session->userdata('id');

        $res = $this->Customer_quote_model->quote_reject_comment($quote_id, $quote_reject_comment, $logged_in_user_id);

        if ($res > 0) {
            $data = array(
                "status" => "success",
                "msg" => "Quote comment inserted."
            );
        } else {
            $data = array(
                "status" => "fail",
                "msg" => "Something went wrong."
            );
        }
        echo json_encode($data);
    }

    public function may_be_quote() {
        $post_data = $this->input->post();
        extract($post_data);
        $quote_id = decrypt_string($quote_id);
        $res = $this->Customer_quote_model->set_status($quote_id, '12', '');
        $res_status = $this->Customer_quote_model->set_quote_status($quote_id, '12');
        if ($res_status > 0) {
            $data = array(
                "status" => "success",
                "msg" => "Quote may be."
            );
        } else {
            $data = array(
                "status" => "fail",
                "msg" => "Something went wrong."
            );
        }
        echo json_encode($data);
    }

    public function quote_accepted() {
        $post_data = $this->input->post();
        extract($post_data);
        $quote_id = decrypt_string($quote_id);
        $res = $this->Customer_quote_model->set_status($quote_id, '2', '');
        $res_status = $this->Customer_quote_model->set_quote_status($quote_id, '2');
        if ($res_status > 0) {
            $data = array(
                "status" => "success",
                "msg" => "Quote accepted."
            );
        } else {
            $data = array(
                "status" => "fail",
                "msg" => "Something went wrong."
            );
        }
        echo json_encode($data);
    }

    public function sign_quote() {
        $post_data = $this->input->post();
        extract($post_data);
        $quote_id = decrypt_string($quote_id);
        $quote_details = $this->Quote_model->getQuoteDetails(md5($quote_id));

        $company_id = getCompanyIdByQuoteId($quote_id);
        $company_dir = "company" . $company_id;
        $dirpath = "uploads/front/company/" . $company_dir . "/";
        $quote_dir_name = "quote" . $quote_id;
        $quote_dir_path = $dirpath . $quote_dir_name;

        $quote_pdf_path = base_url() . $dirpath . 'quote' . $quote_id . '/quote_pdf/';

        if (isset($post_data['quoteCustomerSign'])) {
            $customer_sign_name = date('Y_m_d_H_m_s') . "_customer_sign";
            remove_file($quote_dir_path . "/quote_sign/$quote_details->customer_sign.png");

            $s_res = base64ToImage($quote_dir_path . "/quote_sign/", $customer_sign_name, $post_data['quoteCustomerSign']);
            $logged_in_user_data = $this->session->userdata();
            if ($logged_in_user_data['login_type'] != 'Company') {
                $customer_name = $logged_in_user_data['first_name'] . ' ' . $logged_in_user_data['last_name'];
            } else {
                $customer_name = '';
            }
            if ($s_res) {
                $sign_update_data = array(
                    "customer_sign" => $customer_sign_name,
                    "sign_customer_name" => $customer_name
                );
                $this->Quote_model->update_data("quote_master", array("quote_id" => $quote_id), $sign_update_data);

                $update_html_data = array(
                    "customer_signature" => $quote_dir_path . '/quote_sign/' . $customer_sign_name . '.png',
                    "customer_signature_name" => $customer_name
                );
                $this->db->where("quote_id", $quote_id);
                $this->db->where("version_number", $post_data['version']);
                $this->db->update('quote_version_details', $update_html_data);
            }
        }
        $lang = $this->session->userdata('site_lang');

        //$this->Customer_quote_model->set_status($quote_id, '2', '');
        //$this->Customer_quote_model->set_quote_status($quote_id, '2');

        $this->Customer_quote_model->set_status($quote_id, '1', '');
        $res_status = $this->Customer_quote_model->set_quote_status($quote_id, '1');
        if ($res_status > 0) {
            $language = get_languages();



            foreach ($language as $key => $value) {
                quote_preview($quote_id, $post_data['version'], $value->language_abb);
            }
            $data = array(
                'path' => $quote_pdf_path . 'quote_' . $quote_id . '_' . $lang . '.pdf',
                "status" => "success",
                "msg" => "Quote signed."
            );
        } else {
            $data = array(
                "status" => "fail",
                "msg" => "Something went wrong."
            );
        }
        echo json_encode($data);
    }

    public function request_change_in_quote() {
        $post_data = $this->input->post();

        extract($post_data);
        $quote_id = decrypt_string($quote_id);
        $topic_id = $post_data['request_quote_change_id'];
        $change_request_message = $post_data['request_quote_change_message'];
        $quote_details = $this->Quote_model->getQuoteDetails(md5($quote_id));

        $new_topic_id = $this->Customer_quote_model->request_change_in_quote_message($quote_id, $topic_id, $change_request_message, $quote_details->customer_id);
        if ($new_topic_id > 0) {
            $data = array(
                "status" => "success",
                "msg" => "Changes in quote sent.",
                "topic_id" => $new_topic_id
            );
        } else {
            $data = array(
                "status" => "fail",
                "msg" => "Something went wrong."
            );
        }
        echo json_encode($data);
    }

    public function ask_general_questions() {
        $post_data = $this->input->post();

        extract($post_data);
        $quote_id = decrypt_string($quote_id);
        $topic_id = $post_data['question_topic_id'];
        $quote_question = $post_data['quote_question'];
        $quote_details = $this->Quote_model->getQuoteDetails(md5($quote_id));
        $logged_in_user_id = $this->session->userdata('id');
        $new_topic_id = $this->Customer_quote_model->ask_quote_queries($quote_id, $topic_id, $quote_question, $logged_in_user_id);
        if ($new_topic_id > 0) {
            $data = array(
                "status" => "success",
                "msg" => "Query sent.",
                "topic_id" => $new_topic_id
            );
        } else {
            $data = array(
                "status" => "fail",
                "msg" => "Something went wrong."
            );
        }
        echo json_encode($data);
    }

    function quote_item_table($quoteId) {
        $html = "";
        $section_details = $this->Quote_model->get_data("item_section_master", array("quote_id" => $quoteId));
        foreach ($section_details as $s_d) {
            $html .= '<table width="600px"><tbody>';
            $html .= '<tr style="background:#f1f1f2;color:#231f20;font-size:8px;">
					<td style="padding:10px 20px">
						Row</td>
					<td style="padding:10px">
						Item number</td>
					<td style="padding:10px">
						Title</td>
					<td style="padding:10px">
						Amount</td>
					<td style="padding:10px">
						Unit</td>
					<td style="padding:10px">
						A-price</td>
					<td style="padding:10px">
						Sum</td>
					<td style="padding:10px">
						Tax, %</td>
				</tr>';

            $items_details = $this->Quote_model->get_data('quote_item_master', array('item_section_id' => $s_d->item_section_id));
            $count = 1;
            foreach ($items_details as $i_d) {
                $item_no = $this->Quote_model->get_data('item_master', array('item_master_id' => $i_d->item_master_id));
                $currency = $this->Quote_model->get_data('quote_currency_master', array('q_currency_id' => $i_d->currency_id));
                $unit = $this->Quote_model->get_data($this->item_unit_master, array('item_unit_id' => $i_d->item_unit_id));

                $html .= '<tr style="color:#231f20;font-size:10px;">
					<td style="padding:10px 20px">
						' . $count . '<span style="color:#f1f4f6;float:right;">|</span></td>
					<td style="padding:10px">
						' . @$item_no[0]->item_number . '<span style="color:#f1f4f6;float:right;">|</span></td>
					<td style="padding:10px">
						' . @$item_no[0]->item_name . '<span style="color:#f1f4f6;float:right;">|</span></td>
					<td style="padding:10px">
						' . @$items_details[0]->amount . '<span style="color:#f1f4f6;float:right;">|</span></td>
					<td style="padding:10px">
						' . @$unit[0]->unit_code . '<span style="color:#f1f4f6;float:right;">|</span></td>
					<td style="padding:10px">
						' . @$items_details[0]->a_price . '<span style="color:#f1f4f6;float:right;">|</span></td>
					<td style="padding:10px">
						' . @$items_details[0]->sum . '<span style="color:#f1f4f6;float:right;">|</span></td>
					<td style="padding:10px">
						' . $i_d->item_tax_id . '</td>
				</tr>';
                $count++;
            }

            $dis = $this->Quote_model->get_data('quote_discount_offer', array('item_section_id' => $i_d->item_section_id));
            $type = ($dis[0]->drop_down_value == 'percentage') ? '%' : '';
            $html .= '</tbody></table>';
            $html .= '<table width="600px"><tbody>';
            $html .= '<tr>
					<td style="color:#6f7d95;font-size:8px;padding:3px 0 3px 20px;">
						Freight</td>
					<td style="color:#6f7d95;font-size:8px;">
						invoice free</td>
					<td style="color:#6f7d95;font-size:8px;">
						Discount</td>
					<td style="color:#6f7d95;font-size:8px;">
						Gross</td>
					<td style="color:#6f7d95;font-size:8px;">
						Total excl.</td>
					<td style="color:#6f7d95;font-size:8px;">
						Surface skattered.</td>
					<td style="color:#6f7d95;font-size:8px;">
						Total</td>
				</tr>
				<tr>
					<td style="color:#231f20;font-size:11px;padding:3px 0 3px 20px;">
						' . $s_d->freight . '</td>
					<td style="color:#231f20;font-size:10px;padding:3px 0;">
						' . $s_d->invoice_free . '</td>
					<td style="color:#231f20;font-size:10px;padding:3px 0;">
						' . $s_d->tax_percentage . '</td>
					<td style="color:#231f20;font-size:10px;padding:3px 0;">
						' . $s_d->gross . '</td>
					<td style="color:#231f20;font-size:10px;padding:3px 0;">
						' . $s_d->total_excl . '</td>
					<td style="color:#231f20;font-size:10px;padding:3px 0;">
						' . $s_d->surface_skattered . '</td>
					<td style="color:#231f20;font-size:10px;padding:3px 0;">
						' . $s_d->total . '</td>
				</tr>
				<tr>
					<td colspan="7" style="padding:0px">
						&nbsp;</td>
				</tr>
				<tr>
					<td colspan="4" style="color:#6f7d95;font-size:8px;padding:3px 0 3px 20px;">
						Discount offer ' . $dis[0]->values . ' ' . $type . '</td>
					<td style="color:#6f7d95;font-size:8px;">
						Moms</td>
					<td style="color:#6f7d95;font-size:8px;">
						Tax reduction</td>
					<td style="color:#6f7d95;font-size:8px;">
						To pay</td>
				</tr>
				<tr>
					<td colspan="4" style="color:#231f20;font-size:11px;padding:3px 0 3px 20px;">
						Valid till: ' . date('Y-m-d', strtotime($dis[0]->finish_time)) . '</td>
					<td style="color:#231f20;font-size:10px;padding:3px 0;">
						' . $s_d->moms . '</td>
					<td style="color:#231f20;font-size:10px;padding:3px 0;">
						' . $s_d->tax_reduction . '</td>
					<td style="color:#231f20;font-size:10px;padding:3px 0;">
						' . $s_d->to_pay . ' SEC</td>
				</tr>';
            $html .= '</tbody></table>';
        }
        return $html;
    }

    public function download_pdf($quote_id = "", $language = "", $version = "") {
        if ($quote_id != "") {
            $quote_id = decrypt_string($quote_id);
            $quote_details = $this->Quote_model->getQuoteDetails(md5($quote_id));
            if (count($quote_details) > 0) {
              if($version == ""){
                // get version
                $ver_count = $this->db->query("select count(quote_status_id) as c from quote_status where status_id = 4 AND quote_id = " . $quote_id . " GROUP BY status_id")->row();
                $pdf_version = "_v_" . $ver_count->c;
              }else{
                $pdf_version = "_" . $version;
              }
                $company_id = getCompanyIdByQuoteId($quote_id);
                $company_dir = "company" . $company_id;
                $dirpath = "uploads/front/company/" . $company_dir . "/";
                $quote_dir_name = "quote" . $quote_id;
                $quote_dir_path = $dirpath . $quote_dir_name . "/";
                $this->load->helper('download');
                $fullPath = $quote_dir_path . "quote_pdf/" .  "quote_" . $quote_id . "_" . $language . $pdf_version . "." . "pdf";
                echo $fullPath;
                force_download($fullPath, NULL);
            } else {
                redirect("404");
            }
        } else {
            redirect("404");
        }
    }

}

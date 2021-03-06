<?php

include("../includes/config.inc.php");

$mode = $_POST['mode'];

switch ($mode)
{
    case "like_feed":
        {
            $liked = mysql_clean($_POST['liked']);
            $feed_id = mysql_clean($_POST['feed_id']);


            $results = $cbfeeds->like_feed(array('feed_id' => $feed_id, 'liked' => $liked));
            $total_likes = $results['total_likes'];

            $likes = $results['likes'];
            $results['phrase'] = get_likes_phrase($likes, $total_likes, $liked);

            echo json_encode($results);
        }
        break;

    case "add_feed_comment":
        {
            $id = mysql_clean($_POST['feed_id']);
            $comment = $_POST['comment'];

            if ($comment == 'undefined')
                $comment = '';
            $reply_to = $_POST['reply_to'];

            $cid = $cbfeeds->add_comment($comment, $id, $reply_to);

            if (error())
            {
                exit(json_encode(array('err' => error())));
            }

            $comment = $myquery->get_comment($cid);
            assign('comment', $comment);

            $template = get_template('single_feed_comment');

            $array = array(
                'msg' => msg(),
                'comment' => $template,
                'success' => 'ok',
                'cid' => $cid
            );

            echo json_encode($array);
        }

        break;

    case "add_feed":
    case "add_status":
    case "add_post":
        {
            $object_type = mysql_clean(post('object_type'));
            $object_id = mysql_clean(post('object_id'));

            if ($object_type == 'friend')
                $object_type = 'user';

            if (strstr($object_id, ','))
            {
                $objects = explode(',', $object_id);
                foreach ($objects as $obj)
                {
                    if ($obj)
                    {
                        $object_id = $obj;
                        break;
                    }
                }
            }
            
            if($object_type=='self')
            {
                $object_type = 'user';
                $object_id = userid();
            }


            try
            {

                if (!userid())
                    cb_error(lang('You are not logged in'));

                $object = get_object($object_type, $object_id);

                if (!$object)
                    cb_error(lang('Invalid object request'));


                $content_type = mysql_clean(post('content_type'));
                $content_id = mysql_clean(post('content_id'));
                //$content = post('content');

                if ($content_id)
                {
                    $content = get_object($content_type, $content_id);
                    if (!$content)
                        cb_error(lang('Invalid content request'));
                }

                $post = mysql_clean(post('post'));

                if (!$content && !$post)
                    cb_error(lang('Please enter something for message'));


                $action = mysql_clean(post('action'));
                if (!$action)
                    $action = 'added_status';

                //Lets add the feed....
                $feed_array = array(
                    'userid' => userid(),
                    'user' => $userquery->udetails,
                    'content_id' => $content_id,
                    'content' => $cbfeeds->get_content($content_type, $content_id, $content),
                    'content_type' => $content_type,
                    'object_id' => $object_id,
                    'object' => $object,
                    'object_type' => $object_type,
                    'action' => $action,
                    'message' => $post
                );

                $fid = $cbfeeds->add_feed($feed_array);

                if (error())
                {
                    $error = error('single');
                    cb_error($error);
                }

                //return feed result.
                $feed = $cbfeeds->get_feed($fid);
                assign('feed', $feed);

                if ($object_type)
                {
                    $template = get_template('single_feed_' . $object_type);
                }

                if (!$template)
                    $template = get_template('single_feed');


                $array = array(
                    'success' => 'ok',
                    'template' => $template,
                    'fid' => $fid
                );

                echo json_encode($array);
            }
            catch (Exception $e)
            {

                exit(json_encode(array('err' => array($e->getMessage()))));
            }
        }

        break;



    case "get_notifications":
        {
            $uid = mysql_clean(post('userid'));
            if (!$uid)
                $uid = userid();

            $notifications = $cbfeeds->get_notifications('unread');

            $updates = array();
            if ($notifications)
            {
                $total_new = 0;
                $the_notifications = array();
                $notification_template = '';
                foreach ($notifications as $notification)
                {
                    $the_notifications['ids'][] = $notification['notification_id'];

                    //Lets create a template and append to it..
                    assign('notification', $notification);
                    $notification_template .= get_template('notification_block');
                    $total_new++;
                }

                $the_notifications['template'] = $notification_template;
                $updates['notifications'] = $the_notifications;

                $updates['notifications']['total_new'] = $total_new;
            }
            
            //Read notifications
            if($_POST['read'])
            {
                $userquery->read_notification($uid, 'notification');
                $cbfeeds->read_notifications($uid);
            }
            
            echo json_encode($updates);
        }
        break;

    
    default:
        exit(json_encode(array('err' => array(lang('Invalid request')))));
}
?>

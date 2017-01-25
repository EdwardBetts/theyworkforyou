<?php
/**
 * Policy Positions
 *
 * @package TheyWorkForYou
 */

namespace MySociety\TheyWorkForYou;

class Divisions {

    /**
     * Member
     */

    private $member;

    /**
     * DB handle
     */
    private $db;

    private $positions;
    private $policies;

    /**
     * Constructor
     *
     * @param Member   $member   The member to get positions for.
     */

    public function __construct(Member $member, PolicyPositions $positions = NULL, Policies $policies = NULL)
    {
        $this->member = $member;
        $this->positions = $positions;
        $this->policies = $policies;
        $this->db = new \ParlDB;
    }

    /**
     *
     * Get a list of division votes related to a policy
     *
     * Returns an array with one key ( the policyID ) containing a hash
     * with a policy_id key and a divisions key which contains an array
     * with details of all the divisions.
     *
     * Each division is a hash with the following fields:
     *    division_id, date, vote, gid, url, text, strong
     *
     * @param $policyId The ID of the policy to get divisions for
     */

    public function getMemberDivisionsForPolicy($policyID = null) {
        $where_extra = '';
        $args = array(':person_id' => $this->member->person_id);
        if ( $policyID ) {
            $where_extra = 'AND policy_id = :policy_id';
            $args[':policy_id'] = $policyID;
        }
        $q = $this->db->query(
            "SELECT policy_id, division_id, division_title, yes_text, no_text, division_date, division_number, vote, gid, direction
            FROM policydivisions JOIN persondivisionvotes USING(division_id)
            WHERE person_id = :person_id AND direction <> 'abstention' $where_extra
            ORDER by policy_id, division_date",
            $args
        );

        return $this->divisionsByPolicy($q);
    }

    public function getMemberDivsionDetails() {
        $args = array(':person_id' => $this->member->person_id);

        $policy_divisions = array();

        $q = $this->db->query(
            "SELECT policy_id, count(division_id) as total, max(year(division_date)) as latest, min(year(division_date)) as earliest
            FROM policydivisions JOIN persondivisionvotes USING(division_id)
            WHERE person_id = :person_id AND direction <> 'abstention'
            GROUP BY policy_id",
            $args
        );

        $row_count = $q->rows();
        for ($n = 0; $n < $row_count; $n++) {
          $summary = array(
            'max' => $q->field($n, 'latest'),
            'min' => $q->field($n, 'earliest'),
            'total' => $q->field($n, 'total'),
            'for' => 0, 'against' => 0, 'absent' => 0, 'both' => 0, 'tell' => 0
          );

          $policy_divisions[$q->field($n, 'policy_id')] = $summary;
        }

        $q = $this->db->query(
            "SELECT policy_id, policy_vote, vote, count(division_id) as total
            FROM policydivisions JOIN persondivisionvotes USING(division_id)
            WHERE person_id = :person_id AND direction <> 'abstention'
            GROUP BY policy_id, policy_vote, vote",
            $args
        );

        $row_count = $q->rows();
        for ($n = 0; $n < $row_count; $n++) {
          $summary = $policy_divisions[$q->field($n, 'policy_id')];

          $vote = $q->field($n, 'vote');
          $policy_vote = str_replace('3', '', $q->field($n, 'policy_vote'));
          if ( $vote == 'absent' ) {
              $summary['absent'] += $q->field($n, 'total');
          } else if ( $vote == 'both' ) {
              $summary['both'] += $q->field($n, 'total');
          } else if ( strpos($vote, 'tell') !== FALSE ) {
              $summary['tell'] += $q->field($n, 'total');
          } else if ( $policy_vote == $vote ) {
              $summary['for'] += $q->field($n, 'total');
          } else if ( $policy_vote != $vote ) {
              $summary['against'] += $q->field($n, 'total');
          }

          $policy_divisions[$q->field($n, 'policy_id')] = $summary;
        }

        return $policy_divisions;
    }

    public function getMemberDivsionSummaryForPolicy($policyID) {
        $args = array(':person_id' => $this->member->person_id);
        $args[':policy_id'] = $policyID;

        $q = $this->db->query(
            "SELECT count(division_id) as total, max(year(division_date)) as latest, min(year(division_date)) as earliest
            FROM policydivisions JOIN persondivisionvotes USING(division_id)
            WHERE person_id = :person_id AND direction <> 'abstention' AND policy_id = :policy_id",
            $args
        );

        $max = $q->field(0, 'latest');
        $min = $q->field(0, 'earliest');
        $total = $q->field(0, 'total');

        $q = $this->db->query(
            "SELECT policy_vote, vote, count(division_id) as total
            FROM policydivisions JOIN persondivisionvotes USING(division_id)
            WHERE person_id = :person_id AND direction <> 'abstention' AND policy_id = :policy_id
            GROUP BY policy_vote, vote",
            $args
        );

        $votes = array(
          'max' => $max,
          'min' => $min,
          'total' => $total,
          'for' => 0,
          'against' => 0,
          'absent' => 0,
          'both' => 0,
          'tell' => 0
        );

        $row_count = $q->rows();
        for ($n = 0; $n < $row_count; $n++) {
          $vote = $q->field($n, 'vote');
          $policy_vote = str_replace('3', '', $q->field($n, 'policy_vote'));
          if ( $vote == 'absent' ) {
              $votes['absent'] += $q->field($n, 'total');
          } else if ( $vote == 'both' ) {
              $votes['both'] += $q->field($n, 'total');
          } else if ( strpos($vote, 'tell') !== FALSE ) {
              $votes['tell'] += $q->field($n, 'total');
          } else if ( $policy_vote == $vote ) {
              $votes['for'] += $q->field($n, 'total');
          } else if ( $policy_vote != $vote ) {
              $votes['against'] += $q->field($n, 'total');
          }
        }

        return $this->generateSummary($votes);
    }

    public function generateSummary($votes) {
        $max = $votes['max'];
        $min = $votes['min'];
        $total = $votes['total'];

        $vote_summary =  " For: " . $votes['for'] . " Against: " . $votes['against'];
        if ( $votes['both'] ) {
          $vote_summary .= " Abstained: " . $votes['both'];
        }
        if ( $votes['absent'] ) {
          $vote_summary .= " Absent: " . $votes['absent'];
        }
        if ( $votes['tell'] ) {
          $vote_summary .= " Teller: " . $votes['tell'];
        }
        $vote_str = $this->votePluralise($total);
        if ($max == $min) {
          return "<b>This MP&rsquo;s $vote_str</b> [$min] " . $vote_summary;
        } else {
          return "<b>This MP&rsquo;s $vote_str</b> [$min&ndash;$max] " . $vote_summary;
        }
    }

    private function votePluralise($count) {
      return $count == 1 ? 'vote' : 'votes';
    }

    /**
     *
     * Get all the divisions a member has voted in keyed by policy
     *
     * Returns an array with keys for each policyID, each of these contains
     * the same structure as getMemberDivisionsForPolicy
     *
     */

    public function getAllMemberDivisionsByPolicy() {
        return $this->getMemberDivisionsForPolicy();
    }


    private function constructYesNoVoteDescription($direction, $title, $short_text) {
        $text = ' voted ';
        if ( $short_text ) {
            $text .= $short_text;
        } else {
            $text .= "$direction on <em>$title</em>";
        }

        return $text;
    }


    private function constructVoteDescription($vote, $yes_text, $no_text, $division_title) {
        /*
         * for most post 2010 votes we have nice single sentence summaries of
         * what voting for or against means so we use that if it's there, however
         * we don't have anything nice for people being absent or for pre 2010
         * votes so we need to generate some text using the title of the division
         */

        switch ( strtolower($vote) ) {
            case 'yes':
            case 'aye':
                $description = $this->constructYesNoVoteDescription('yes', $division_title, $yes_text);
                break;
            case 'no':
                $description = $this->constructYesNoVoteDescription('no', $division_title, $no_text);
                break;
            case 'absent':
                $description = ' was absent for a vote on <em>' . $division_title . '</em>';
                break;
            case 'both':
                $description = ' abstained on a vote on <em>' . $division_title . '</em>';
                break;
            case 'tellyes':
            case 'tellno':
            case 'tellaye':
                $description = ' acted as teller for a vote on <em>' . $division_title . '</em>';
                break;
            default:
                $description = $division_title;
        }

        return $description;
    }

    private function divisionsByPolicy($q) {
        $policies = array();

        $row_count = $q->rows();
        for ($n = 0; $n < $row_count; $n++) {
            $policy_id = $q->field($n, 'policy_id');

            if ( !array_key_exists($policy_id, $policies) ) {
                $policies[$policy_id] = array(
                    'policy_id' => $policy_id,
                    'weak_count' => 0,
                    'divisions' => array()
                );
                if ( $this->policies ) {
                    $policies[$policy_id]['desc'] = $this->policies->getPolicies()[$policy_id];
                    $policies[$policy_id]['header'] = $this->policies->getPolicyDetails($policy_id);
                }
                if ( $this->positions ) {
                    $policies[$policy_id]['position'] = $this->positions->positionsById[$policy_id];
                }
            }
            $division = array();

            $direction = $q->field($n, 'direction');
            if ( strpos( $direction, 'strong') !== FALSE ) {
                $division['strong'] = TRUE;
            } else {
                $division['strong'] = FALSE;
            }

            $vote = $q->field($n, 'vote');
            $yes_text = $q->field($n, 'yes_text');
            $no_text = $q->field($n, 'no_text');
            $division_title = $q->field($n, 'division_title');

            $division['text'] = $this->constructVoteDescription($vote, $yes_text, $no_text, $division_title);
            $division['division_id'] = $q->field($n, 'division_id');
            $division['date'] = $q->field($n, 'division_date');
            $division['vote'] = $vote;
            $division['gid'] = fix_gid_from_db($q->field($n, 'gid'));
            $division['url'] = $this->divisionUrlFromGid($q->field($n, 'gid'));

            if ( !$division['strong'] ) {
                $policies[$policy_id]['weak_count']++;
            }

            $policies[$policy_id]['divisions'][] = $division;
        };

        return $policies;
    }

    private function divisionUrlFromGid($gid) {
        global $hansardmajors;

        $q = $this->db->query("SELECT gid, major FROM hansard WHERE epobject_id = ( SELECT subsection_id FROM hansard WHERE gid = :gid )", array( ':gid' => $gid ));
        $parent_gid = $q->field(0, 'gid');
        if ( !$parent_gid ) {
            return '';
        }
        $parent_gid = fix_gid_from_db($parent_gid);
        $url = new \URL($hansardmajors[$q->field(0, 'major')]['page']);
        $url->insert(array('gid' => $parent_gid));
        return $url->generate();
    }
}

<?php

namespace App\Enums;

enum ThreatFactorKey: string
{
    case Sentiment = 'sentiment';
    case Severity = 'severity';
    case SourceCredibility = 'source_credibility';
    case SerpVisibility = 'serp_visibility';
    case ClusterSize = 'cluster_size';
    case MentionRecency = 'mention_recency';
    case PersonImportance = 'person_importance';
}
